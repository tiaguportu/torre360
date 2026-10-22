<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TempoAula;
use App\Models\Turno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tempos de aula dos turnos (Onda 64): ler o sinal da escola, medir quantas aulas vale um horário da grade e gerar os
 * tempos de um turno a partir do horário de início, da duração e do intervalo.
 *
 * Horários são comparados como texto "HH:MM", que ordena igual ao relógio.
 */
class TempoAulaService
{
    /**
     * @return Collection<int, TempoAula>
     */
    public function doTurno(?int $turnoId): Collection
    {
        if (! $turnoId) {
            return collect();
        }

        return TempoAula::query()->where('turno_id', $turnoId)->orderBy('ordem')->get()->values();
    }

    /**
     * Quantas aulas vale um horário da grade: os tempos do turno que cabem inteiros nele. Horário que não bate com os
     * tempos (ou turno sem tempos) vale uma aula, para a conferência com a matriz nunca zerar uma aula que existe.
     *
     * @param  Collection<int, TempoAula>  $tempos
     */
    public function aulasDoHorario(string $inicio, string $fim, Collection $tempos): int
    {
        $inicio = substr($inicio, 0, 5);
        $fim = substr($fim, 0, 5);

        $dentro = $tempos->filter(fn (TempoAula $tempo): bool => $tempo->inicio() >= $inicio && $tempo->fim() <= $fim)->count();

        return max(1, $dentro);
    }

    /**
     * Tempos que o horário ocupa, mesmo que só em parte: servem para marcar o que a turma ou o professor não tem livre.
     *
     * @param  Collection<int, TempoAula>  $tempos
     * @return Collection<int, TempoAula>
     */
    public function temposOcupados(string $inicio, string $fim, Collection $tempos): Collection
    {
        $inicio = substr($inicio, 0, 5);
        $fim = substr($fim, 0, 5);

        return $tempos->filter(fn (TempoAula $tempo): bool => $tempo->inicio() < $fim && $tempo->fim() > $inicio)->values();
    }

    /**
     * O tempo do turno que se cruza com o intervalo informado, se houver.
     */
    public function sobreposicao(int $turnoId, string $inicio, string $fim, ?int $ignorarId = null): ?TempoAula
    {
        $inicio = substr($inicio, 0, 5);
        $fim = substr($fim, 0, 5);

        return $this->doTurno($turnoId)
            ->first(fn (TempoAula $tempo): bool => $tempo->id !== $ignorarId && $tempo->inicio() < $fim && $tempo->fim() > $inicio);
    }

    /**
     * Cria os tempos de um turno: `$quantidade` tempos de `$duracaoMinutos`, começando em `$inicio`, com um intervalo de
     * `$intervaloMinutos` depois do `$intervaloApos`-ésimo tempo. Se o turno já tem tempos, só substitui com `$substituir`.
     *
     * @return Collection<int, TempoAula>
     *
     * @throws \DomainException
     */
    public function gerar(Turno $turno, string $inicio, int $quantidade, int $duracaoMinutos, ?int $intervaloApos = null, int $intervaloMinutos = 0, bool $substituir = false): Collection
    {
        if ($quantidade < 1 || $quantidade > 12) {
            throw new \DomainException('A quantidade de tempos precisa ficar entre 1 e 12.');
        }

        if ($duracaoMinutos < 10 || $duracaoMinutos > 180) {
            throw new \DomainException('A duração de cada tempo precisa ficar entre 10 e 180 minutos.');
        }

        if ($intervaloMinutos < 0 || $intervaloMinutos > 120) {
            throw new \DomainException('O intervalo precisa ficar entre 0 e 120 minutos.');
        }

        $relogio = Carbon::createFromFormat('H:i', substr($inicio, 0, 5));
        $limite = $relogio->copy()->endOfDay();
        $novos = [];

        for ($ordem = 1; $ordem <= $quantidade; $ordem++) {
            $fim = $relogio->copy()->addMinutes($duracaoMinutos);

            if ($fim->gt($limite) || $fim->lt($relogio)) {
                throw new \DomainException('Os tempos passam da meia-noite. Comece mais cedo ou use menos tempos.');
            }

            $novos[] = ['ordem' => $ordem, 'hora_inicio' => $relogio->format('H:i:s'), 'hora_fim' => $fim->format('H:i:s')];
            $relogio = $fim->copy();

            if ($intervaloApos === $ordem && $intervaloMinutos > 0) {
                $relogio->addMinutes($intervaloMinutos);
            }
        }

        return DB::transaction(function () use ($turno, $novos, $substituir): Collection {
            if ($turno->temposAula()->exists()) {
                if (! $substituir) {
                    throw new \DomainException('Este turno já tem tempos de aula. Marque "substituir os tempos atuais" para refazer.');
                }

                $turno->temposAula()->delete();
            }

            foreach ($novos as $tempo) {
                $turno->temposAula()->create($tempo);
            }

            return $this->doTurno($turno->id);
        });
    }
}
