<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReuniaoService;
use Illuminate\Console\Command;

/**
 * Avisa, uma vez só, o responsável e a coordenação de cada encaminhamento de reunião que passou do prazo (Ondas 27 e 32). A reunião
 * sigilosa não leva o aviso à coordenação.
 */
class LembrarEncaminhamentosReuniaoCommand extends Command
{
    protected $signature = 'reunioes:lembrar-encaminhamentos';

    protected $description = 'Avisa responsável e coordenação sobre encaminhamentos de reunião com o prazo vencido';

    public function handle(ReuniaoService $reunioes): int
    {
        $avisados = $reunioes->lembrarEncaminhamentosVencidos();

        $this->info($avisados === 0
            ? 'Nenhum encaminhamento vencido sem aviso.'
            : "{$avisados} encaminhamento(s) vencido(s) avisado(s).");

        return self::SUCCESS;
    }
}
