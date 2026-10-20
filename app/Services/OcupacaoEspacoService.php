<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusReservaEspaco;
use App\Models\GradeHorario;
use App\Models\ReservaEspaco;
use App\Models\Sala;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Monta, dia a dia, o que ocupa um espaço: as reservas pontuais (solicitadas ou confirmadas) e as aulas da grade
 * horária fixa que de fato acontecem naquela data (fora do período letivo ou em dia não letivo a grade não conta).
 */
class OcupacaoEspacoService
{
    public const MAXIMO_DIAS = 31;

    public function __construct(
        protected ReservaEspacoConflitoService $conflitos,
    ) {}

    /**
     * @return list<array{
     *     data: CarbonInterface,
     *     blocos: list<array{tipo: string, inicio: string, fim: string, titulo: string, detalhe: ?string, status: ?StatusReservaEspaco}>
     * }>
     */
    public function mapa(Sala $sala, CarbonInterface $dataInicial, int $dias = 7): array
    {
        $dias = max(1, min($dias, self::MAXIMO_DIAS));
        $inicio = Carbon::parse($dataInicial->toDateString())->startOfDay();
        $fim = $inicio->copy()->addDays($dias - 1);

        $reservasPorData = ReservaEspaco::query()
            ->queOcupam()
            ->where('sala_id', $sala->id)
            ->whereDate('data', '>=', $inicio->toDateString())
            ->whereDate('data', '<=', $fim->toDateString())
            ->with(['solicitante', 'turma'])
            ->get()
            ->groupBy(fn (ReservaEspaco $reserva): string => $reserva->data->toDateString());

        $gradesPorDiaDaSemana = GradeHorario::query()
            ->where('sala_id', $sala->id)
            ->with(['turma.periodoLetivo', 'turma.serie', 'disciplina'])
            ->get()
            ->groupBy('dia_semana');

        $mapa = [];

        for ($i = 0; $i < $dias; $i++) {
            $data = $inicio->copy()->addDays($i);
            $blocos = [];

            foreach ($reservasPorData->get($data->toDateString(), collect()) as $reserva) {
                $detalhe = collect([$reserva->solicitante?->name, $reserva->turma?->nome ? 'Turma '.$reserva->turma->nome : null])->filter()->implode(' · ');

                $blocos[] = [
                    'tipo' => 'reserva',
                    'inicio' => substr((string) $reserva->hora_inicio, 0, 5),
                    'fim' => substr((string) $reserva->hora_fim, 0, 5),
                    'titulo' => $reserva->motivo,
                    'detalhe' => $detalhe !== '' ? $detalhe : null,
                    'status' => $reserva->status,
                ];
            }

            foreach ($gradesPorDiaDaSemana->get($data->dayOfWeek, collect()) as $grade) {
                if (! $this->conflitos->turmaTemAulaNaData($grade->turma, $data)) {
                    continue;
                }

                $blocos[] = [
                    'tipo' => 'grade',
                    'inicio' => substr((string) $grade->hora_inicio, 0, 5),
                    'fim' => substr((string) $grade->hora_fim, 0, 5),
                    'titulo' => $grade->disciplina?->nome ?? 'Aula',
                    'detalhe' => $grade->turma?->nome ? 'Turma '.$grade->turma->nome.' (grade fixa)' : 'Grade fixa',
                    'status' => null,
                ];
            }

            usort($blocos, fn (array $a, array $b): int => [$a['inicio'], $a['fim']] <=> [$b['inicio'], $b['fim']]);

            $mapa[] = ['data' => $data, 'blocos' => $blocos];
        }

        return $mapa;
    }
}
