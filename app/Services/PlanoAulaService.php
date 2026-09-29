<?php

namespace App\Services;

use App\Models\CronogramaAula;
use App\Models\PlanoAula;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Transforma um plano de aula planejado em um registro real no diário
 * (`CronogramaAula`), levando o conteúdo previsto e as habilidades da BNCC.
 */
class PlanoAulaService
{
    public function executar(PlanoAula $plano, CarbonInterface|string|null $data = null): CronogramaAula
    {
        if ($plano->foiExecutado()) {
            throw new InvalidArgumentException('Este plano de aula já foi executado.');
        }

        $dataAula = Carbon::parse($data ?? $plano->data_prevista);

        $cronograma = CronogramaAula::create([
            'turma_id' => $plano->turma_id,
            'disciplina_id' => $plano->disciplina_id,
            'pessoa_id' => $plano->professor_id,
            'data' => $dataAula->toDateString(),
            'conteudo_ministrado' => $plano->objetivos,
            'anexo_material' => $plano->anexo_material,
        ]);

        $habilidadeIds = $plano->habilidades()->pluck('habilidades.id');
        if ($habilidadeIds->isNotEmpty()) {
            $cronograma->habilidades()->attach($habilidadeIds);
        }

        $plano->update([
            'cronograma_aula_id' => $cronograma->id,
            'executado_em' => now(),
        ]);

        return $cronograma;
    }
}
