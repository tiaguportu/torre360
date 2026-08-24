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
        $interessados = Interessado::ativos()->get();

        if ($interessados->isEmpty()) {
            $this->info('Nenhum lead ativo encontrado.');

            return self::SUCCESS;
        }

        foreach ($interessados as $interessado) {
            LeadScoreService::recalcular($interessado);
        }

        $this->info("Lead Score recalculado para {$interessados->count()} lead(s) ativo(s).");

        return self::SUCCESS;
    }
}
