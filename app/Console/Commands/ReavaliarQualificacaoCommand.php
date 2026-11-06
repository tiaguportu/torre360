<?php

namespace App\Console\Commands;

use App\Models\InteressadoQualificacao;
use App\Services\Qualificacao\QualificacaoService;
use Illuminate\Console\Command;

/**
 * Calcula o resultado do roteiro de qualificação dos leads que já tinham série, turno ou nascimento antes de o roteiro
 * existir. Rodar uma vez depois do deploy; depois disso o resultado acompanha cada alteração do lead e dos alunos.
 */
class ReavaliarQualificacaoCommand extends Command
{
    protected $signature = 'crm:reavaliar-qualificacao {--todos : Inclui também os leads encerrados (matriculados e perdidos)}';

    protected $description = 'Calcula o resultado do roteiro de qualificação dos leads já cadastrados';

    public function handle(QualificacaoService $servico): int
    {
        $total = $servico->reavaliarTodos((bool) $this->option('todos'));

        $porResultado = InteressadoQualificacao::query()
            ->selectRaw('resultado, COUNT(*) as total')
            ->groupBy('resultado')
            ->pluck('total', 'resultado');

        $this->info("{$total} lead(s) reavaliado(s).");

        foreach (InteressadoQualificacao::RESULTADOS as $resultado => $rotulo) {
            $this->line(sprintf('  %s: %d', $rotulo, (int) ($porResultado[$resultado] ?? 0)));
        }

        return self::SUCCESS;
    }
}
