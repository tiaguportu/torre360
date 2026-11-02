<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PropostaAceiteService;
use Illuminate\Console\Command;

/**
 * Todo dia: avisa o consultor das propostas que vencem em 3 e em 1 dia e expira as que passaram da validade (Onda 87).
 */
class ExpirarPropostasComerciaisCommand extends Command
{
    protected $signature = 'crm:expirar-propostas';

    protected $description = 'Avisa o vencimento e expira as propostas comerciais fora da validade';

    public function handle(PropostaAceiteService $servico): int
    {
        $avisadas = $servico->avisarVencimentos();
        $expiradas = $servico->expirarVencidas();

        $this->info("{$avisadas} aviso(s) de vencimento; {$expiradas} proposta(s) expirada(s).");

        return self::SUCCESS;
    }
}
