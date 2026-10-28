<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LimparRelatoriosGeradosTemporariosCommand extends Command
{
    /**
     * O nome e a assinatura do comando no console.
     *
     * @var string
     */
    protected $signature = 'relatorios:limpar-temporarios 
                            {--dias=7 : Quantidade de dias para considerar o relatório expirado (padrão: 7 dias / 1 semana)} 
                            {--dry-run : Apenas simula a limpeza sem excluir os arquivos}';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Expurga arquivos de relatórios em PDF temporários gerados em fila na pasta relatorios_gerados com mais de 1 semana (ou dias configurados)';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        if ($dias <= 0) {
            $dias = 7;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limiteTimestamp = now()->subDays($dias)->timestamp;
        $disco = Storage::disk('local');
        $diretorio = 'relatorios_gerados';
        $arquivos = $disco->files($diretorio);
        $removidos = 0;
        $preservados = 0;
        $bytesLiberados = 0;

        foreach ($arquivos as $arquivo) {
            // Apenas arquivos PDF
            if (! str_ends_with(strtolower($arquivo), '.pdf')) {
                continue;
            }

            $modificadoEm = $disco->lastModified($arquivo);

            if ($modificadoEm < $limiteTimestamp) {
                $tamanho = $disco->size($arquivo);

                if ($dryRun) {
                    $this->line("[DRY-RUN] Seria removido: {$arquivo} (".round($tamanho / 1024, 2).' KB)');
                } else {
                    $disco->delete($arquivo);
                }

                $removidos++;
                $bytesLiberados += $tamanho;
            } else {
                $preservados++;
            }
        }

        $kbytesLiberados = round($bytesLiberados / 1024, 2);
        $prefixo = $dryRun ? '[DRY-RUN] ' : '';
        $acao = $dryRun ? 'identificado(s) para remoção' : 'removido(s)';

        $this->info("{$prefixo}Limpeza de relatórios temporários concluída: {$removidos} arquivo(s) {$acao} ({$kbytesLiberados} KB liberados).");
        $this->info("{$preservados} arquivo(s) recente(s) preservado(s) (limite: {$dias} dias).");

        if (! $dryRun && $removidos > 0) {
            Log::info("Limpeza de relatórios temporários concluída: {$removidos} arquivo(s) removido(s) ({$kbytesLiberados} KB liberados). {$preservados} recente(s) preservado(s) (limite: {$dias} dias).");
        }

        return self::SUCCESS;
    }
}
