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

    private function ehPendente(string $capa): bool
    {
        return str_starts_with($capa, LivroLookupService::DIRETORIO_PENDENTES.'/');
    }
}
