<?php

namespace App\Observers;

use App\Models\FrequenciaEscolar;
use App\Services\AtestadoAlunoService;

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
        // Abonar exige justificar: o abono sem justificativa não vale (a falta segue contando na frequência).
        if ($frequencia->abonada && ! $frequencia->justificada) {
            $frequencia->abonada = false;
        }

        // Só a transição conta: salvar de novo uma falta que já era falta não a reenvia nem desfaz um envio já feito.
        if (! $frequencia->isDirty('situacao')) {
            return;
        }

        if ($frequencia->situacao === 'ausente') {
            $this->justificarSeHouverAtestado($frequencia);
            $frequencia->aviso_resumo_pendente = $this->deveAvisar($frequencia);

            return;
        }

        // Falta que deixou de ser falta: o que o atestado deu (justificativa e abono) não faz mais sentido; se voltar a ser
        // falta, é reavaliado.
        if ($frequencia->atestado_aluno_id !== null) {
            $frequencia->justificada = false;
            $frequencia->abonada = false;
            $frequencia->motivo_justificativa = null;
            $frequencia->atestado_aluno_id = null;
        }

        // Corrigida antes do resumo (ausente -> presente): sai da fila de avisos.
        if ($frequencia->aviso_resumo_pendente) {
            $frequencia->aviso_resumo_pendente = false;
        }
    }

    /**
     * Falta lançada depois do atestado (ou dentro do período, que a secretaria registra antes de passar): já nasce
     * justificada e, se o atestado foi registrado como abono, abonada. Não mexe em falta que alguém já justificou à mão.
     * A marca de aviso segue a regra normal: o resumo diário ignora falta justificada na hora do envio e, se o atestado
     * for cancelado, a falta volta à fila.
     */
    private function justificarSeHouverAtestado(FrequenciaEscolar $frequencia): void
    {
        if ($frequencia->justificada) {
            return;
        }

        $frequencia->loadMissing('cronogramaAula');

        $data = $frequencia->cronogramaAula?->data;

        if ($data === null) {
            return;
        }

        $atestado = app(AtestadoAlunoService::class)->atestadoVigenteEm((int) $frequencia->matricula_id, $data);

        if ($atestado === null) {
            return;
        }

        $frequencia->justificada = true;
        $frequencia->abonada = $atestado->abona_faltas;
        $frequencia->motivo_justificativa = AtestadoAlunoService::motivoDaJustificativa($atestado);
        $frequencia->atestado_aluno_id = $atestado->id;
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
