<?php

namespace Tests\Unit\Policies;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\Support\UsuarioComPermissoes;
use Tests\TestCase;

/**
 * Quase toda policy do sistema é o molde do Shield: `return $authUser->can('Metodo:Modelo');`. O defeito típico é um
 * erro de digitação na permissão (ou o nome de outro modelo copiado junto), que deixa a tela aberta a quem não deveria
 * ou fechada a quem deveria. Aqui cada método no molde é conferido: pergunta exatamente a permissão `Metodo:Modelo`
 * (salvo os desvios deliberados abaixo) e devolve o que o usuário tem. Os métodos com regra própria (estado, posse)
 * têm testes à parte.
 */
class PoliticasNoMoldeDoShieldTest extends TestCase
{
    /**
     * Métodos que, de propósito, consultam outra permissão que não `Metodo:Modelo`. Fixados aqui para que uma troca
     * sem querer apareça como falha.
     *
     * @var array<string, array<string, string>>
     */
    private const DESVIOS_DELIBERADOS = [
        // uma única permissão de gestão para todo o comitê
        'DenunciaComitePolicy' => [
            'viewAny' => 'Gerenciar:DenunciaComite',
            'view' => 'Gerenciar:DenunciaComite',
            'create' => 'Gerenciar:DenunciaComite',
            'update' => 'Gerenciar:DenunciaComite',
            'delete' => 'Gerenciar:DenunciaComite',
            'deleteAny' => 'Gerenciar:DenunciaComite',
        ],
        // não há permissão DeleteAny própria: vale a de excluir
        'DisponibilidadeVisitaPolicy' => ['deleteAny' => 'Delete:DisponibilidadeVisita'],
        'PlanoDesenvolvimentoDocentePolicy' => ['deleteAny' => 'Delete:PlanoDesenvolvimentoDocente'],
        // a movimentação de patrimônio herda as permissões do bem
        'MovimentacaoPatrimonioPolicy' => [
            'viewAny' => 'ViewAny:BemPatrimonial',
            'view' => 'View:BemPatrimonial',
            'create' => 'Update:BemPatrimonial',
            'update' => 'Update:BemPatrimonial',
            'delete' => 'Delete:BemPatrimonial',
            'deleteAny' => 'DeleteAny:BemPatrimonial',
        ],
        // a linha de aluno e o encaminhamento pertencem à reunião
        'ReuniaoAlunoPolicy' => ['viewAny' => 'ViewAny:Reuniao'],
        'ReuniaoEncaminhamentoPolicy' => ['viewAny' => 'ViewAny:Reuniao', 'create' => 'Update:Reuniao'],
    ];

    /**
     * Policies que usam outro padrão de nome de permissão, de propósito.
     *
     * @var array<string, string>
     */
    private const FORA_DO_PADRAO = [
        'AnotacaoOsPolicy' => 'usa o padrão antigo do Shield (view_any_anotacao::os)',
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function policies(): array
    {
        $casos = [];

        foreach (glob(dirname(__DIR__, 3).'/app/Policies/*Policy.php') as $arquivo) {
            $nome = basename($arquivo, '.php');
            $casos[$nome] = [$nome];
        }

        return $casos;
    }

    #[DataProvider('policies')]
    public function test_cada_metodo_no_molde_consulta_a_permissao_do_proprio_modelo(string $nome): void
    {
        if (isset(self::FORA_DO_PADRAO[$nome])) {
            $this->markTestSkipped($nome.': '.self::FORA_DO_PADRAO[$nome]);
        }

        $classe = "App\\Policies\\{$nome}";
        $policy = new $classe;
        $modelo = substr($nome, 0, -strlen('Policy'));
        $desvios = [];
        $conferidos = 0;

        foreach ((new ReflectionClass($classe))->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
            if ($metodo->getDeclaringClass()->getName() !== $classe || $metodo->isConstructor()) {
                continue;
            }

            $permissao = $this->permissaoDoMolde($metodo);
            $argumentos = $permissao === null ? null : $this->argumentos($metodo);
            if ($argumentos === null) {
                continue; // regra própria: testada à parte
            }

            if ($permissao !== ucfirst($metodo->getName()).':'.$modelo) {
                $desvios[$metodo->getName()] = $permissao;
            }

            $this->assertFalse(
                $metodo->invoke($policy, new UsuarioComPermissoes([]), ...$argumentos),
                "{$nome}::{$metodo->getName()} liberou quem não tem {$permissao}",
            );
            $this->assertTrue(
                $metodo->invoke($policy, new UsuarioComPermissoes([$permissao]), ...$argumentos),
                "{$nome}::{$metodo->getName()} negou quem tem {$permissao}",
            );

            $conferidos++;
        }

        $this->assertSame(self::DESVIOS_DELIBERADOS[$nome] ?? [], $desvios, "{$nome} consulta permissão diferente de Metodo:{$modelo}");

        if ($conferidos === 0) {
            $this->markTestSkipped("{$nome} não tem método no molde do Shield");
        }
    }

    /**
     * A permissão consultada, se o corpo do método for exatamente `return $x->can('Permissao');`; senão null.
     */
    private function permissaoDoMolde(ReflectionMethod $metodo): ?string
    {
        $linhas = file($metodo->getFileName());
        $corpo = implode('', array_slice($linhas, $metodo->getStartLine() - 1, $metodo->getEndLine() - $metodo->getStartLine() + 1));

        $achou = preg_match('/\)\s*(?::\s*\??\w+)?\s*\{\s*return \$\w+->can\(\'([A-Za-z]+:[A-Za-z0-9]+)\'\);\s*\}\s*$/s', $corpo, $m);

        return $achou ? $m[1] : null;
    }

    /**
     * Instâncias vazias para os parâmetros depois do usuário (modelos sem banco); null se não der para montar.
     *
     * @return list<mixed>|null
     */
    private function argumentos(ReflectionMethod $metodo): ?array
    {
        $argumentos = [];

        foreach (array_slice($metodo->getParameters(), 1) as $parametro) {
            $tipo = $parametro->getType();

            if (! $tipo instanceof ReflectionNamedType || $tipo->isBuiltin()) {
                return null;
            }

            $classe = $tipo->getName();
            if (! is_a($classe, Model::class, true)) {
                return null;
            }

            $argumentos[] = new $classe;
        }

        return $argumentos;
    }
}
