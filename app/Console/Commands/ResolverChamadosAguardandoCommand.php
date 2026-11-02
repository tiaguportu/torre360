<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AtendimentoService;
use Illuminate\Console\Command;

/**
 * Chamado "aguardando a família" há mais dias que o setor define (padrão 7) fica resolvido sozinho e a família é avisada.
 * Se ela responder depois, o chamado reabre.
 */
class ResolverChamadosAguardandoCommand extends Command
{
    protected $signature = 'atendimento:resolver-aguardando';

    protected $description = 'Resolve os chamados que esperam a resposta da família há dias demais';

    public function handle(AtendimentoService $servico): int
    {
        $total = $servico->resolverAguardandoVencidos();

        $this->info($total === 0 ? 'Nenhum chamado parado esperando a família.' : "{$total} chamado(s) resolvido(s) por falta de resposta.");

        return self::SUCCESS;
    }
}
