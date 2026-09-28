<?php

namespace App\Services;

use App\Models\CronogramaAula;
use App\Models\DiaNaoLetivo;
use App\Models\Matricula;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Agenda semanal de aulas de um aluno para o Portal da Família, montada a
 * partir do cronograma (aulas datadas) da turma da matrícula.
 */
class HorarioAlunoService
{
    private const NOMES_DIAS = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * Segunda-feira da semana que contém a data informada.
     */
    public static function inicioDaSemana(CarbonInterface $data): Carbon
    {
        return Carbon::parse($data->toDateString())->startOfWeek(CarbonInterface::MONDAY);
    }

    /**
     * Dias da semana (segunda a sexta sempre; sábado e domingo só quando há aula ou dia não letivo).
     *
     * @return array{inicio: Carbon, fim: Carbon, dias: list<array{data: Carbon, nome: string, nao_letivo: ?string, aulas: Collection<int, CronogramaAula>}>}
     */
    public function semana(Matricula $matricula, CarbonInterface $referencia): array
    {
        $inicio = self::inicioDaSemana($referencia);
        $fim = $inicio->copy()->addDays(6);

        $aulas = $matricula->turma_id
            ? CronogramaAula::query()
                ->where('turma_id', $matricula->turma_id)
                ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
                ->with(['disciplina', 'professor'])
                ->orderBy('hora_inicio')
                ->orderBy('id')
                ->get()
                ->groupBy(fn (CronogramaAula $aula): string => $aula->data->toDateString())
            : collect();

        $naoLetivos = $this->diasNaoLetivos($matricula, $inicio, $fim);

        $dias = [];

        for ($i = 0; $i < 7; $i++) {
            $data = $inicio->copy()->addDays($i);
            $chave = $data->toDateString();
            $aulasDoDia = $aulas->get($chave, collect());
            $naoLetivo = $naoLetivos[$chave] ?? null;

            if ($data->isoWeekday() > 5 && $aulasDoDia->isEmpty() && $naoLetivo === null) {
                continue;
            }

            $dias[] = [
                'data' => $data,
                'nome' => self::NOMES_DIAS[$data->isoWeekday()],
                'nao_letivo' => $naoLetivo,
                'aulas' => $aulasDoDia,
            ];
        }

        return ['inicio' => $inicio, 'fim' => $fim, 'dias' => $dias];
    }

    /**
     * Dias não letivos (feriados/recessos) do período e curso da turma, indexados por data (Y-m-d).
     *
     * @return array<string, string>
     */
    private function diasNaoLetivos(Matricula $matricula, Carbon $inicio, Carbon $fim): array
    {
        $turma = $matricula->turma;

        if (! $turma || ! $turma->periodo_letivo_id) {
            return [];
        }

        $cursoId = $turma->serie?->curso_id;

        return DiaNaoLetivo::query()
            ->where('periodo_letivo_id', $turma->periodo_letivo_id)
            ->where('flag_ativo', true)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->where(fn ($query) => $query->whereNull('curso_id')->when($cursoId, fn ($q) => $q->orWhere('curso_id', $cursoId)))
            ->get()
            ->mapWithKeys(fn (DiaNaoLetivo $dia): array => [Carbon::parse($dia->data)->toDateString() => (string) $dia->descricao])
            ->all();
    }
}
