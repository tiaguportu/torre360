<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DiaNaoLetivo;
use App\Models\GradeHorario;
use App\Models\ReservaEspaco;
use App\Models\Turma;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Descobre por que um espaço não está livre numa data e faixa de horário: outra reserva (solicitada ou
 * confirmada) ou a grade horária semanal fixa. É a mesma ideia de `GradeHorarioConflitoService`, trocando o "dia da
 * semana recorrente" por uma data específica.
 *
 * A grade só conta quando a turma realmente tem aula naquele dia: fora do período letivo dela ou num dia não letivo
 * (feriado, recesso) a sala está livre, mesmo que a grade diga que ela seria usada naquele dia da semana.
 */
class ReservaEspacoConflitoService
{
    /**
     * @return list<string> Mensagens de conflito (vazio = espaço livre).
     */
    public function conflitos(int $salaId, CarbonInterface $data, string $horaInicio, string $horaFim, ?int $ignorarReservaId = null): array
    {
        $inicio = self::normalizarHora($horaInicio);
        $fim = self::normalizarHora($horaFim);
        $mensagens = [];

        $reservas = ReservaEspaco::query()
            ->queOcupam()
            ->sobrepoe($salaId, $data->toDateString(), $inicio, $fim)
            ->when($ignorarReservaId, fn ($q) => $q->where('id', '!=', $ignorarReservaId))
            ->orderBy('hora_inicio')
            ->get();

        foreach ($reservas as $reserva) {
            $situacao = $reserva->aguardaAprovacao() ? ' (aguardando aprovação)' : '';
            $mensagens[] = "O espaço já está reservado das {$reserva->intervaloFormatado()}{$situacao} para \"{$reserva->motivo}\".";
        }

        $grades = GradeHorario::query()
            ->where('sala_id', $salaId)
            ->where('dia_semana', $data->dayOfWeek)
            ->with(['turma.periodoLetivo', 'turma.serie', 'disciplina'])
            ->orderBy('hora_inicio')
            ->get();

        foreach ($grades as $grade) {
            if (! $this->horariosSeCruzam(self::normalizarHora((string) $grade->hora_inicio), self::normalizarHora((string) $grade->hora_fim), $inicio, $fim)) {
                continue;
            }

            if (! $this->turmaTemAulaNaData($grade->turma, $data)) {
                continue;
            }

            $disciplina = $grade->disciplina?->nome ?? 'aula';
            $mensagens[] = 'A grade horária fixa ocupa a sala das '.substr((string) $grade->hora_inicio, 0, 5).' às '.substr((string) $grade->hora_fim, 0, 5)." neste dia da semana (turma \"{$grade->turma?->nome}\", {$disciplina}).";
        }

        return $mensagens;
    }

    /**
     * Normaliza qualquer representação de hora (`14:00`, `14:00:00`, datetime) para `HH:MM:SS`.
     */
    public static function normalizarHora(string $hora): string
    {
        return Carbon::parse($hora)->format('H:i:s');
    }

    /**
     * Faixas que apenas se tocam (uma termina quando a outra começa) não se cruzam.
     */
    private function horariosSeCruzam(string $inicioA, string $fimA, string $inicioB, string $fimB): bool
    {
        return $inicioA < $fimB && $fimA > $inicioB;
    }

    /**
     * A turma tem aula nesta data? Falso fora do período letivo dela e em dias não letivos (gerais ou do curso).
     */
    public function turmaTemAulaNaData(?Turma $turma, CarbonInterface $data): bool
    {
        if (! $turma) {
            return true;
        }

        $periodo = $turma->periodoLetivo;

        if ($periodo?->data_inicio && $data->copy()->startOfDay()->lt($periodo->data_inicio->copy()->startOfDay())) {
            return false;
        }

        if ($periodo?->data_fim && $data->copy()->startOfDay()->gt($periodo->data_fim->copy()->startOfDay())) {
            return false;
        }

        if (! $turma->periodo_letivo_id) {
            return true;
        }

        $cursoId = $turma->serie?->curso_id;

        $diaNaoLetivo = DiaNaoLetivo::query()
            ->where('periodo_letivo_id', $turma->periodo_letivo_id)
            ->where('flag_ativo', true)
            ->whereDate('data', $data->toDateString())
            ->where(fn ($q) => $q->whereNull('curso_id')->when($cursoId, fn ($qq) => $qq->orWhere('curso_id', $cursoId)))
            ->exists();

        return ! $diaNaoLetivo;
    }
}
