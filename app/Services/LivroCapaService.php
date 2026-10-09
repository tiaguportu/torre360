<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Livro;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ciclo de vida do arquivo de capa de um livro (disco `public`).
 *
 * A busca por ISBN baixa a capa para {@see LivroLookupService::DIRETORIO_PENDENTES}, com nome fixo por ISBN. Só ao
 * salvar o livro ela vira definitiva: ganha um arquivo próprio daquele registro (duas obras com o mesmo ISBN não
 * dividem arquivo — apagar a capa de uma não quebra a outra). Capas abandonadas (formulário fechado sem salvar) são
 * limpas por `biblioteca:limpar-capas-pendentes`; trocar a capa ou excluir o livro apaga o arquivo antigo.
 */
class LivroCapaService
{
    private const DIRETORIO_CAPAS = 'livros/capas/';

    /**
     * Se a capa do livro ainda é um arquivo pendente, copia-o para um arquivo definitivo e aponta o livro para ele.
     * Chamado antes de gravar o livro.
     */
    public function promoverPendente(Livro $livro): void
    {
        $capa = $livro->capa;

        if (! is_string($capa) || ! $this->ehPendente($capa)) {
            return;
        }

        $disco = Storage::disk('public');

        try {
            if (! $disco->exists($capa)) {
                // Passou o prazo de limpeza com o formulário aberto: sem arquivo, não há como manter a referência.
                $livro->capa = null;

                return;
            }

            $extensao = pathinfo($capa, PATHINFO_EXTENSION) ?: 'jpg';
            $base = preg_replace('/[^0-9A-Z]/', '', strtoupper((string) $livro->isbn)) ?: 'livro';
            $destino = self::DIRETORIO_CAPAS.$base.'_'.Str::random(8).'.'.$extensao;

            // Copia (e não move): outro formulário aberto com o mesmo ISBN ainda pode estar apontando para o pendente.
            $disco->copy($capa, $destino);
            $livro->capa = $destino;
        } catch (\Throwable $e) {
            report($e);
            $livro->capa = null;
        }
    }

    /**
     * Apaga o arquivo de uma capa que deixou de ser usada, se for um arquivo local definitivo e nenhum outro livro
     * ainda apontar para ele.
     */
    public function descartar(?string $capa, ?int $ignorarLivroId = null): void
    {
        if (blank($capa) || str_starts_with($capa, 'http') || ! str_starts_with($capa, self::DIRETORIO_CAPAS) || $this->ehPendente($capa)) {
            return;
        }

        $emUso = Livro::query()
            ->where('capa', $capa)
            ->when($ignorarLivroId, fn ($consulta) => $consulta->where('id', '!=', $ignorarLivroId))
            ->exists();

        if ($emUso) {
            return;
        }

        try {
            Storage::disk('public')->delete($capa);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Remove capas pendentes mais antigas que o prazo. Retorna quantos arquivos foram apagados.
     */
    public function limparPendentes(int $horas = 48): int
    {
        $disco = Storage::disk('public');
        $limite = now()->subHours(max(1, $horas))->getTimestamp();
        $apagados = 0;

        foreach ($disco->files(LivroLookupService::DIRETORIO_PENDENTES) as $arquivo) {
            if ($disco->lastModified($arquivo) < $limite) {
                $disco->delete($arquivo);
                $apagados++;
            }
        }

        return $apagados;
    }

    /**
     * Compara os arquivos de `livros/capas/` com o que os livros realmente usam e separa os órfãos.
     *
     * Um arquivo só é órfão se nenhum livro o referencia E ele não foi modificado nos últimos `$dias` dias (o prazo
     * protege capas que acabaram de ser copiadas e ainda não tiveram o livro gravado). Subpastas (inclusive
     * `pendentes/`, que tem limpeza própria) e arquivos ocultos nunca entram.
     *
     * `referenciadas` e `ausentes` permitem desconfiar de um armazenamento que não pertence ao banco consultado:
     * se NENHUMA capa em uso existe no disco, todo arquivo pareceria órfão.
     *
     * @return array{
     *     orfas: list<array{caminho: string, bytes: int, modificado: int}>,
     *     em_uso: int,
     *     recentes: int,
     *     referenciadas: int,
     *     ausentes: int
     * }
     */
    public function diagnosticarOrfas(int $dias = 7): array
    {
        $disco = Storage::disk('public');
        $limite = now()->subDays(max(1, $dias))->getTimestamp();

        // Só capas locais contam como referência (URL remota não ocupa o disco).
        $referenciadas = Livro::query()
            ->whereNotNull('capa')
            ->where('capa', '!=', '')
            ->pluck('capa')
            ->map(fn ($capa): string => $this->normalizar((string) $capa))
            ->reject(fn (string $capa): bool => str_starts_with($capa, 'http'))
            ->unique()
            ->values();

        $ausentes = $referenciadas->reject(fn (string $capa): bool => $disco->exists($capa))->count();
        $emUsoPorCaminho = $referenciadas->flip();

        $orfas = [];
        $emUso = 0;
        $recentes = 0;

        foreach ($disco->files(rtrim(self::DIRETORIO_CAPAS, '/')) as $arquivo) {
            if (str_starts_with(basename($arquivo), '.')) {
                continue;
            }

            if ($emUsoPorCaminho->has($arquivo)) {
                $emUso++;

                continue;
            }

            $modificado = $disco->lastModified($arquivo);

            if ($modificado >= $limite) {
                $recentes++;

                continue;
            }

            $orfas[] = ['caminho' => $arquivo, 'bytes' => $disco->size($arquivo), 'modificado' => $modificado];
        }

        return [
            'orfas' => $orfas,
            'em_uso' => $emUso,
            'recentes' => $recentes,
            'referenciadas' => $referenciadas->count(),
            'ausentes' => $ausentes,
        ];
    }

    /**
     * Apaga os arquivos informados, conferindo de novo (no momento de apagar) que continuam fora de `pendentes/`,
     * dentro de `livros/capas/` e sem nenhum livro apontando para eles. Retorna quantos foram apagados.
     *
     * @param  list<string>  $caminhos
     */
    public function apagarOrfas(array $caminhos): int
    {
        $disco = Storage::disk('public');
        $apagados = 0;

        foreach ($caminhos as $caminho) {
            $caminho = $this->normalizar($caminho);

            if (! str_starts_with($caminho, self::DIRETORIO_CAPAS)
                || $this->ehPendente($caminho)
                || str_contains($caminho, '..')
                || str_contains(substr($caminho, strlen(self::DIRETORIO_CAPAS)), '/')) {
                continue;
            }

            if (Livro::query()->whereIn('capa', [$caminho, '/'.$caminho])->exists()) {
                continue;
            }

            try {
                if ($disco->delete($caminho)) {
                    $apagados++;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $apagados;
    }

    private function normalizar(string $caminho): string
    {
        return ltrim(str_replace('\\', '/', trim($caminho)), '/');
    }

    private function ehPendente(string $capa): bool
    {
        return str_starts_with($capa, LivroLookupService::DIRETORIO_PENDENTES.'/');
    }
}
