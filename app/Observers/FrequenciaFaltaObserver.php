<?php

namespace App\Observers;

use App\Models\FrequenciaEscolar;

/**
 * Ao registrar (ou alterar para) uma falta, marca-a como pendente de resumo: o aviso à família sai uma vez por dia,
 * junto com as demais faltas (`ResumoFaltasDiarioService`), em vez de um e-mail por aula. Não marca se a aula ocorreu
 * fora da janela em que a matrícula estava ativa (evita alarme para lançamentos retroativos/fora de vigência).
 *
 * A marca é gravada junto com a própria falta (evento `saving`), sem segunda consulta. Os responsáveis só são
 * resolvidos na hora do envio, então quem for cadastrado antes do resumo do dia também recebe.
 */
class FrequenciaFaltaObserver
{
    public function saving(FrequenciaEscolar $frequencia): void
    {
        // Só a transição conta: salvar de novo uma falta que já era falta não a reenvia nem desfaz um envio já feito.
        if (! $frequencia->isDirty('situacao')) {
            return;
        }

        if ($frequencia->situacao === 'ausente') {
            $frequencia->aviso_resumo_pendente = $this->deveAvisar($frequencia);

            return;
        }

        // Corrigida antes do resumo (ausente -> presente): sai da fila de avisos.
        if ($frequencia->aviso_resumo_pendente) {
            $frequencia->aviso_resumo_pendente = false;
        }
    }

    private function deveAvisar(FrequenciaEscolar $frequencia): bool
    {
        $frequencia->loadMissing(['matricula.pessoa', 'cronogramaAula']);

        $matricula = $frequencia->matricula;
        $aula = $frequencia->cronogramaAula;

        if (! $matricula || ! $matricula->pessoa || ! $aula) {
            return false;
        }

        return $this->matriculaEstavaAtivaEm($matricula, $aula->data);
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
