<?php

namespace App\Console\Commands;

use App\Models\Interessado;
use App\Services\LeadScoreService;
use Illuminate\Console\Command;

class RecalcularLeadScoreCommand extends Command
{
    protected $signature = 'crm:recalcular-lead-score';

    protected $description = 'Recalcula o Lead Score de todos os leads ativos (necessário porque o fator de recência decai com o tempo, mesmo sem nenhuma interação nova)';

    public function handle(): int
    {
        $total = 0;

        // Em lotes: carregar todos os leads de uma vez e recalcular um a um (com `refresh()` e uma consulta
        // de etapas por lead) multiplicava as consultas pelo tamanho da base. Cada lote carrega as relações
        // de todos os seus leads em uma consulta por relação.
        Interessado::ativos()->chunkById(200, function ($lote) use (&$total): void {
            $total += LeadScoreService::recalcularLote($lote);
        });

        if ($total === 0) {
            $this->info('Nenhum lead ativo encontrado.');

            return self::SUCCESS;
        }

        $this->info("Lead Score recalculado para {$total} lead(s) ativo(s).");

        return self::SUCCESS;
    }
}
