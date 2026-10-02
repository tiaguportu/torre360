<?php

namespace App\Console\Commands;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use App\Services\RiscoEvasaoService;
use Illuminate\Console\Command;

class RecalcularRiscoEvasaoCommand extends Command
{
    protected $signature = 'academico:recalcular-risco-evasao';

    protected $description = 'Recalcula o Risco de Evasao de todas as matriculas ativas (necessario porque o fator de frequencia decai com o tempo, mesmo sem nenhuma falta nova)';

    public function handle(): int
    {
        $total = 0;

        Matricula::query()->where('situacao', SituacaoMatricula::ATIVA)
            ->chunkById(200, function ($matriculas) use (&$total): void {
                foreach ($matriculas as $matricula) {
                    RiscoEvasaoService::recalcular($matricula);
                    $total++;
                }
            });

        if ($total === 0) {
            $this->info('Nenhuma matrícula ativa encontrada.');

            return self::SUCCESS;
        }

        $this->info("Risco de Evasão recalculado para {$total} matrícula(s) ativa(s).");

        return self::SUCCESS;
    }
}
