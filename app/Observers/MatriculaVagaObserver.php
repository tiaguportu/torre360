<?php

namespace App\Observers;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusListaEspera;
use App\Models\ListaEsperaMatricula;
use App\Models\Matricula;
use App\Models\Turma;

class MatriculaVagaObserver
{
    /**
     * Situações que efetivamente ocupam uma vaga na turma. Note que isso é mais
     * preciso do que a contagem usada hoje em `EnrollmentWizard::save()`
     * (`$turma->matriculas()->count()`, sem filtro de situação) — ali, uma
     * matrícula cancelada continua contando como vaga ocupada. Por isso é
     * possível, num caso raro, a lista de espera notificar uma vaga que o
     * assistente de matrícula ainda bloquearia; ajustar essa contagem no
     * assistente é uma correção separada, fora do escopo desta onda.
     */
    private const SITUACOES_QUE_OCUPAM_VAGA = [
        SituacaoMatricula::ATIVA,
        SituacaoMatricula::PENDENTE,
        SituacaoMatricula::RESERVA,
    ];

    public function created(Matricula $matricula): void
    {
        $this->marcarConvertidoSeHouverEntrada($matricula);
    }

    public function updated(Matricula $matricula): void
    {
        $this->marcarConvertidoSeHouverEntrada($matricula);

        if ($matricula->wasChanged('situacao') || $matricula->wasChanged('turma_id')) {
            $this->notificarProximoDaFilaSeHouverVaga($matricula->turma_id);

            if ($matricula->wasChanged('turma_id')) {
                $this->notificarProximoDaFilaSeHouverVaga($matricula->getOriginal('turma_id'));
            }
        }
    }

    public function deleted(Matricula $matricula): void
    {
        $this->notificarProximoDaFilaSeHouverVaga($matricula->turma_id);
    }

    /**
     * Quando uma matrícula é criada para a mesma pessoa+turma de uma entrada na
     * lista de espera ainda aberta, considera a espera resolvida.
     */
    private function marcarConvertidoSeHouverEntrada(Matricula $matricula): void
    {
        ListaEsperaMatricula::where('turma_id', $matricula->turma_id)
            ->where('pessoa_id', $matricula->pessoa_id)
            ->whereIn('status', [StatusListaEspera::Aguardando->value, StatusListaEspera::Notificado->value])
            ->update(['status' => StatusListaEspera::Convertido->value]);
    }

    /**
     * Reavalia a ocupação da turma (matrículas em situação que ocupa vaga vs.
     * vagas_maximas) e notifica o próximo da fila de espera (FIFO por
     * created_at) se houver vaga e fila.
     */
    private function notificarProximoDaFilaSeHouverVaga(?int $turmaId): void
    {
        if (! $turmaId) {
            return;
        }

        $turma = Turma::find($turmaId);

        if (! $turma || ! $turma->vagas_maximas) {
            return;
        }

        $ocupadas = $turma->matriculas()
            ->whereIn('situacao', array_map(fn (SituacaoMatricula $s) => $s->value, self::SITUACOES_QUE_OCUPAM_VAGA))
            ->count();

        if ($ocupadas >= $turma->vagas_maximas) {
            return;
        }

        $proximo = ListaEsperaMatricula::where('turma_id', $turmaId)
            ->where('status', StatusListaEspera::Aguardando->value)
            ->oldest()
            ->first();

        $proximo?->notificarVagaDisponivel();
    }
}
