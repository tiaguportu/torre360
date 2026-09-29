<?php

namespace App\Observers;

use App\Models\FrequenciaEscolar;
use App\Notifications\FrequenciaAusenciaNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Ao registrar (ou alterar para) uma falta, avisa o(s) responsável(is) do
 * aluno. Não notifica se a aula ocorreu fora da janela em que a matrícula
 * estava ativa (evita alarme para lançamentos retroativos/fora de vigência).
 */
class FrequenciaFaltaObserver
{
    public function created(FrequenciaEscolar $frequencia): void
    {
        if ($frequencia->situacao === 'ausente') {
            $this->notificar($frequencia);
        }
    }

    public function updated(FrequenciaEscolar $frequencia): void
    {
        if ($frequencia->situacao === 'ausente' && $frequencia->getOriginal('situacao') !== 'ausente') {
            $this->notificar($frequencia);
        }
    }

    private function notificar(FrequenciaEscolar $frequencia): void
    {
        $frequencia->loadMissing(['matricula.pessoa.responsaveis.users', 'cronogramaAula']);

        $matricula = $frequencia->matricula;
        $aluno = $matricula?->pessoa;
        $aula = $frequencia->cronogramaAula;

        if (! $matricula || ! $aluno || ! $aula) {
            return;
        }

        if (! $this->matriculaEstavaAtivaEm($matricula, $aula->data)) {
            return;
        }

        $usuarios = $aluno->responsaveis
            ->flatMap(fn ($responsavel) => $responsavel->users)
            ->unique('id');

        if ($usuarios->isNotEmpty()) {
            Notification::send($usuarios, new FrequenciaAusenciaNotification($frequencia));
        }
    }

    private function matriculaEstavaAtivaEm($matricula, $dataAula): bool
    {
        if ($dataAula === null) {
            return true;
        }

        $ativacao = $matricula->data_ativacao;
        $desativacao = $matricula->data_desativacao;

        if ($ativacao !== null && $dataAula->lt($ativacao)) {
            return false;
        }

        if ($desativacao !== null && $dataAula->gte($desativacao)) {
            return false;
        }

        return true;
    }
}
