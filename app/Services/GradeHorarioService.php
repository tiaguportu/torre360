<?php

namespace App\Services;

use App\Models\CronogramaAula;
use App\Models\DiaNaoLetivo;
use App\Models\Turma;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Gera o cronograma de aulas (diário datado) de uma turma a partir da sua
 * grade horária semanal, respeitando os dias não letivos do período. Não
 * duplica aulas já existentes na mesma data/disciplina/horário.
 */
class GradeHorarioService
{
    /**
     * @return int Quantidade de aulas criadas no cronograma.
     */
    public function gerarCronograma(Turma $turma, ?CarbonInterface $dataInicio = null, ?CarbonInterface $dataFim = null): int
    {
        $grades = $turma->gradeHorarios()->with(['disciplina', 'professor'])->get();

        if ($grades->isEmpty()) {
            return 0;
        }

        $periodo = $turma->periodoLetivo;
        $inicio = Carbon::parse($dataInicio ?? $periodo?->data_inicio ?? now());
        $fim = Carbon::parse($dataFim ?? $periodo?->data_fim ?? now());

        if ($fim->lt($inicio)) {
            return 0;
        }

        $diasNaoLetivos = $this->diasNaoLetivos($turma, $inicio, $fim);

        $existentes = CronogramaAula::query()
            ->where('turma_id', $turma->id)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->get(['disciplina_id', 'data', 'hora_inicio'])
            ->map(fn (CronogramaAula $a) => "{$a->disciplina_id}|{$a->data->toDateString()}|{$a->hora_inicio}")
            ->flip();

        $novasAulas = [];
        $agora = now();

        for ($data = $inicio->copy(); $data->lte($fim); $data->addDay()) {
            if (isset($diasNaoLetivos[$data->toDateString()])) {
                continue;
            }

            foreach ($grades as $grade) {
                if ($grade->dia_semana !== $data->dayOfWeek) {
                    continue;
                }

                $chave = "{$grade->disciplina_id}|{$data->toDateString()}|{$grade->hora_inicio}";
                if (isset($existentes[$chave])) {
                    continue;
                }

                $novasAulas[] = [
                    'turma_id' => $turma->id,
                    'disciplina_id' => $grade->disciplina_id,
                    'pessoa_id' => $grade->professor_id,
                    'data' => $data->toDateString(),
                    'hora_inicio' => $grade->hora_inicio,
                    'hora_fim' => $grade->hora_fim,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
                $existentes[$chave] = true;
            }
        }

        if ($novasAulas === []) {
            return 0;
        }

        foreach (array_chunk($novasAulas, 200) as $lote) {
            CronogramaAula::insert($lote);
        }

        return count($novasAulas);
    }

    /**
     * Dias não letivos do período (gerais ou do curso da turma), indexados por data (Y-m-d).
     *
     * @return array<string, true>
     */
    private function diasNaoLetivos(Turma $turma, Carbon $inicio, Carbon $fim): array
    {
        $periodoLetivoId = $turma->periodo_letivo_id;

        if (! $periodoLetivoId) {
            return [];
        }

        $cursoId = $turma->serie?->curso_id;

        return DiaNaoLetivo::query()
            ->where('periodo_letivo_id', $periodoLetivoId)
            ->where('flag_ativo', true)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->where(fn ($q) => $q->whereNull('curso_id')->when($cursoId, fn ($qq) => $qq->orWhere('curso_id', $cursoId)))
            ->pluck('data')
            ->mapWithKeys(fn ($data) => [Carbon::parse($data)->toDateString() => true])
            ->all();
    }
}
