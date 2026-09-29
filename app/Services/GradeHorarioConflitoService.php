<?php

namespace App\Services;

use App\Models\GradeHorario;

/**
 * Detecta sobreposição de horário na grade semanal: mesma turma, mesmo
 * professor ou mesma sala não podem ter dois horários que se cruzem no mesmo
 * dia da semana. Mesma lógica de intervalo já usada em
 * `CronogramaAulas\Pages\VerificaConflitos`, aplicada aqui antes de salvar.
 */
class GradeHorarioConflitoService
{
    /**
     * @return list<string> Mensagens de conflito (vazio = sem conflito).
     */
    public function conflitos(GradeHorario $grade): array
    {
        $mensagens = [];

        $conflitoTurma = $this->buscarConflito($grade, 'turma_id', $grade->turma_id);
        if ($conflitoTurma) {
            $mensagens[] = "A turma já tem \"{$conflitoTurma->disciplina?->nome}\" das {$this->formatarHora($conflitoTurma->hora_inicio)} às {$this->formatarHora($conflitoTurma->hora_fim)} neste dia.";
        }

        if ($grade->professor_id) {
            $conflitoProfessor = $this->buscarConflito($grade, 'professor_id', $grade->professor_id);
            if ($conflitoProfessor) {
                $mensagens[] = "O professor já está escalado na turma \"{$conflitoProfessor->turma?->nome}\" das {$this->formatarHora($conflitoProfessor->hora_inicio)} às {$this->formatarHora($conflitoProfessor->hora_fim)} neste dia.";
            }
        }

        if ($grade->sala_id) {
            $conflitoSala = $this->buscarConflito($grade, 'sala_id', $grade->sala_id);
            if ($conflitoSala) {
                $mensagens[] = "A sala já está reservada para a turma \"{$conflitoSala->turma?->nome}\" das {$this->formatarHora($conflitoSala->hora_inicio)} às {$this->formatarHora($conflitoSala->hora_fim)} neste dia.";
            }
        }

        return $mensagens;
    }

    private function buscarConflito(GradeHorario $grade, string $coluna, mixed $valor): ?GradeHorario
    {
        return GradeHorario::query()
            ->where($coluna, $valor)
            ->where('dia_semana', $grade->dia_semana)
            ->when($grade->exists, fn ($q) => $q->where('id', '!=', $grade->id))
            ->where('hora_inicio', '<', $grade->hora_fim)
            ->where('hora_fim', '>', $grade->hora_inicio)
            ->with(['turma', 'disciplina'])
            ->first();
    }

    private function formatarHora(mixed $hora): string
    {
        return substr((string) $hora, 0, 5);
    }
}
