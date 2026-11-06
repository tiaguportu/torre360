<?php

namespace App\Jobs;

use App\Services\Qualificacao\QualificacaoService;
use App\Support\FilaLonga;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Recalcula o resultado do roteiro de qualificação de todos os leads em andamento. Roda quando a secretaria muda o que é
 * necessário para marcar a visita (ou quais campos estão ativos): sem isso, os leads existentes continuariam com o
 * resultado calculado pela regra antiga. Fila longa: com milhares de leads leva mais que uma requisição.
 */
class ReavaliarQualificacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct()
    {
        FilaLonga::direcionar($this);
    }

    public function handle(QualificacaoService $servico): void
    {
        $servico->reavaliarTodos();
    }
}
