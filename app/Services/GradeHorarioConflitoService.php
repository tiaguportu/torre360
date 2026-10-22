<?php

namespace App\Services;

use App\Models\GradeHorario;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detecta sobreposição de horário na grade semanal: mesma turma, mesmo
 * professor ou mesma sala não podem ter dois horários que se cruzem no mesmo
 * dia da semana. Mesma lógica de intervalo já usada em
 * `CronogramaAulas\Pages\VerificaConflitos`, aplicada aqui antes de salvar.
 *
 * Professor e sala só conflitam com turmas cujo período letivo se cruza com o da turma do horário, e turma cancelada
 * não ocupa ninguém: a grade do ano passado, que continua no banco, não pode travar a deste ano.
 */
class GradeHorarioConflitoService
{
    /**
     * @return list<string> Mensagens de conflito (vazio = sem conflito).
     */
    public function conflitos(GradeHorario $grade): array
    {
        $mensagens = [];
        $periodo = $this->periodoDaTurma($grade->turma_id);

        $conflitoTurma = $this->buscarConflito($grade, 'turma_id', $grade->turma_id, null);
        if ($conflitoTurma) {
            $mensagens[] = "A turma já tem \"{$conflitoTurma->disciplina?->nome}\" das {$this->formatarHora($conflitoTurma->hora_inicio)} às {$this->formatarHora($conflitoTurma->hora_fim)} neste dia.";
        }

        if ($grade->professor_id) {
            $conflitoProfessor = $this->buscarConflito($grade, 'professor_id', $grade->professor_id, $periodo);
            if ($conflitoProfessor) {
                $mensagens[] = "O professor já está escalado na turma \"{$conflitoProfessor->turma?->nome}\" das {$this->formatarHora($conflitoProfessor->hora_inicio)} às {$this->formatarHora($conflitoProfessor->hora_fim)} neste dia.";
            }
        }

        if ($grade->sala_id) {
            $conflitoSala = $this->buscarConflito($grade, 'sala_id', $grade->sala_id, $periodo);
            if ($conflitoSala) {
                $mensagens[] = "A sala já está reservada para a turma \"{$conflitoSala->turma?->nome}\" das {$this->formatarHora($conflitoSala->hora_inicio)} às {$this->formatarHora($conflitoSala->hora_fim)} neste dia.";
            }
        }

        return $mensagens;
    }

    /**
     * Avisos que não impedem salvar: o professor informou que não pode dar aula nesse horário. Quem monta a grade pode
     * ter combinado outra coisa, então é aviso, não bloqueio.
     *
     * @return list<string>
     */
    public function avisos(GradeHorario $grade): array
    {
        if (! $grade->professor_id || $grade->dia_semana === null || ! $grade->hora_inicio || ! $grade->hora_fim) {
            return [];
        }

        $servico = app(DisponibilidadeProfessorService::class);
        $janelas = $servico->janelasDe([$grade->professor_id])->get($grade->professor_id, collect());
        $motivo = $servico->motivoDeIndisponibilidade($janelas, (int) $grade->dia_semana, (string) $grade->hora_inicio, (string) $grade->hora_fim);

        return $motivo ? ["O professor está {$motivo} neste horário."] : [];
    }

    /**
     * Restringe horários da grade às turmas que dividem o calendário com o período informado (os períodos se cruzam) e
     * que não foram canceladas.
     *
     * @param  Builder<GradeHorario>  $query
     * @return Builder<GradeHorario>
     */
    public function escopoDoPeriodo(Builder $query, ?PeriodoLetivo $periodo): Builder
    {
        return $query->whereHas('turma', function (Builder $turma) use ($periodo): void {
            $turma->where('status', '!=', 'cancelada');

            if ($periodo) {
                $turma->whereHas('periodoLetivo', fn (Builder $p) => $p
                    ->whereDate('data_inicio', '<=', $periodo->data_fim->toDateString())
                    ->whereDate('data_fim', '>=', $periodo->data_inicio->toDateString()));
            }
        });
    }

    private function periodoDaTurma(mixed $turmaId): ?PeriodoLetivo
    {
        return $turmaId ? Turma::query()->with('periodoLetivo')->find($turmaId)?->periodoLetivo : null;
    }

    private function buscarConflito(GradeHorario $grade, string $coluna, mixed $valor, ?PeriodoLetivo $periodo): ?GradeHorario
    {
        $query = GradeHorario::query()
            ->where($coluna, $valor)
            ->where('dia_semana', $grade->dia_semana)
            ->when($grade->exists, fn ($q) => $q->where('id', '!=', $grade->id))
            ->where('hora_inicio', '<', $grade->hora_fim)
            ->where('hora_fim', '>', $grade->hora_inicio)
            ->with(['turma', 'disciplina']);

        if ($coluna !== 'turma_id') {
            $this->escopoDoPeriodo($query, $periodo);
        }

        return $query->first();
    }

    private function formatarHora(mixed $hora): string
    {
        return substr((string) $hora, 0, 5);
    }
}
