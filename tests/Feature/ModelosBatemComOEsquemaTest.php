<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Erro de digitação em nome de coluna ou de chave estrangeira só aparece quando alguém usa o campo (e, no SQLite, nem
 * sempre: o nome entre aspas vira texto). Aqui todo modelo é conferido contra o esquema migrado: a tabela existe, o
 * `$fillable` aponta para colunas reais e cada relacionamento declarado resolve (classe relacionada, tabela, chaves e
 * colunas de pivô). Os problemas são juntados e mostrados de uma vez.
 */
class ModelosBatemComOEsquemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Desvios conhecidos e deliberados, no formato "Modelo: descrição do problema".
     *
     * @var list<string>
     */
    private const CONHECIDOS = [];

    /**
     * @return list<class-string<Model>>
     */
    private function modelos(): array
    {
        $classes = [];
        $raiz = dirname(__DIR__, 2).'/app/Models';

        $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterador as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            $relativo = str_replace(['/', '\\'], '\\', substr($arquivo->getPathname(), strlen($raiz) + 1, -4));
            $classe = "App\\Models\\{$relativo}";

            if (! class_exists($classe)) {
                continue;
            }

            $reflexao = new ReflectionClass($classe);
            if ($reflexao->isAbstract() || ! $reflexao->isSubclassOf(Model::class)) {
                continue;
            }

            $classes[] = $classe;
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function colunas(string $tabela): array
    {
        return Schema::hasTable($tabela) ? Schema::getColumnListing($tabela) : [];
    }

    public function test_todo_modelo_tem_tabela_fillable_e_relacionamentos_coerentes_com_o_esquema(): void
    {
        $problemas = [];

        foreach ($this->modelos() as $classe) {
            $curto = class_basename($classe);

            try {
                $modelo = new $classe;
            } catch (\Throwable $e) {
                $problemas[] = "{$curto}: não instancia ({$e->getMessage()})";

                continue;
            }

            $tabela = $modelo->getTable();
            if (! Schema::hasTable($tabela)) {
                $problemas[] = "{$curto}: a tabela '{$tabela}' não existe";

                continue;
            }

            $colunas = $this->colunas($tabela);

            foreach ($modelo->getFillable() as $campo) {
                if (! in_array($campo, $colunas, true)) {
                    $problemas[] = "{$curto}: fillable '{$campo}' não é coluna de '{$tabela}'";
                }
            }

            foreach ((new ReflectionClass($classe))->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
                if ($metodo->getDeclaringClass()->getName() !== $classe || $metodo->isStatic() || $metodo->getNumberOfRequiredParameters() > 0) {
                    continue;
                }

                $tipo = $metodo->getReturnType();
                if (! $tipo instanceof ReflectionNamedType || $tipo->isBuiltin() || ! is_a($tipo->getName(), Relation::class, true)) {
                    continue;
                }

                $this->conferirRelacionamento($modelo, $curto, $tabela, $colunas, $metodo->getName(), $problemas);
            }
        }

        $problemas = array_values(array_diff($problemas, self::CONHECIDOS));

        $this->assertSame([], $problemas, "\n".implode("\n", $problemas));
    }

    /**
     * @param  list<string>  $colunas  colunas da tabela do próprio modelo
     * @param  list<string>  $problemas
     */
    private function conferirRelacionamento(Model $modelo, string $curto, string $tabela, array $colunas, string $nome, array &$problemas): void
    {
        $rotulo = "{$curto}::{$nome}()";

        try {
            $relacao = $modelo->{$nome}();
        } catch (\Throwable $e) {
            $problemas[] = "{$rotulo}: não monta o relacionamento ({$e->getMessage()})";

            return;
        }

        $relacionado = $relacao->getRelated();
        $tabelaRelacionada = $relacionado->getTable();

        if (! Schema::hasTable($tabelaRelacionada)) {
            $problemas[] = "{$rotulo}: a tabela relacionada '{$tabelaRelacionada}' não existe";

            return;
        }

        $colunasRelacionada = $this->colunas($tabelaRelacionada);

        if ($relacao instanceof MorphTo) {
            return; // o alvo só se conhece pelo registro
        }

        if ($relacao instanceof BelongsTo) {
            if (! in_array($relacao->getForeignKeyName(), $colunas, true)) {
                $problemas[] = "{$rotulo}: a chave '{$relacao->getForeignKeyName()}' não é coluna de '{$tabela}'";
            }

            return;
        }

        if ($relacao instanceof MorphOneOrMany) {
            foreach ([$relacao->getForeignKeyName(), $relacao->getMorphType()] as $coluna) {
                if (! in_array($coluna, $colunasRelacionada, true)) {
                    $problemas[] = "{$rotulo}: '{$coluna}' não é coluna de '{$tabelaRelacionada}'";
                }
            }

            return;
        }

        if ($relacao instanceof HasOneOrMany) {
            if (! in_array($relacao->getForeignKeyName(), $colunasRelacionada, true)) {
                $problemas[] = "{$rotulo}: a chave '{$relacao->getForeignKeyName()}' não é coluna de '{$tabelaRelacionada}'";
            }

            return;
        }

        if ($relacao instanceof BelongsToMany) {
            $pivo = $relacao->getTable();
            if (! Schema::hasTable($pivo)) {
                $problemas[] = "{$rotulo}: a tabela de pivô '{$pivo}' não existe";

                return;
            }

            $colunasPivo = $this->colunas($pivo);
            $esperadas = array_merge(
                [$relacao->getForeignPivotKeyName(), $relacao->getRelatedPivotKeyName()],
                $relacao->getPivotColumns(),
            );

            foreach ($esperadas as $coluna) {
                if (! in_array($coluna, $colunasPivo, true)) {
                    $problemas[] = "{$rotulo}: '{$coluna}' não é coluna do pivô '{$pivo}'";
                }
            }

            return;
        }

        if ($relacao instanceof HasOneThrough || $relacao instanceof HasManyThrough) {
            return; // tabela relacionada já conferida acima
        }
    }
}
