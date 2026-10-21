<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusParecerDescritivo;
use App\Exceptions\ParecerDescritivoException;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\ParecerDescritivo;
use App\Models\User;

/**
 * Fluxo do parecer descritivo: o professor escreve (rascunho), envia para revisão, a coordenação publica para a
 * família. Publicado, o texto só volta a ser editado depois de reaberto por quem pode publicar.
 */
class ParecerDescritivoService
{
    /** Tamanho mínimo do texto para seguir no fluxo: evita publicar um parecer vazio ou de uma linha. */
    public const TAMANHO_MINIMO = 80;

    /**
     * @throws ParecerDescritivoException
     */
    public function iniciar(Matricula $matricula, EtapaAvaliativa $etapa, User $autor): ParecerDescritivo
    {
        if ($matricula->situacao !== SituacaoMatricula::ATIVA) {
            throw new ParecerDescritivoException('Só alunos com matrícula ativa recebem parecer.');
        }

        if ((int) $etapa->turma_id !== (int) $matricula->turma_id) {
            throw new ParecerDescritivoException('A etapa avaliativa não pertence à turma do aluno.');
        }

        return ParecerDescritivo::firstOrCreate(
            ['matricula_id' => $matricula->id, 'etapa_avaliativa_id' => $etapa->id],
            ['status' => StatusParecerDescritivo::Rascunho, 'autor_user_id' => $autor->id],
        );
    }

    /**
     * @throws ParecerDescritivoException
     */
    public function enviarParaRevisao(ParecerDescritivo $parecer, User $por): ParecerDescritivo
    {
        if ($parecer->status !== StatusParecerDescritivo::Rascunho) {
            throw new ParecerDescritivoException('Só um rascunho pode ser enviado para revisão.');
        }

        $this->exigirTexto($parecer);

        $parecer->update(['status' => StatusParecerDescritivo::EmRevisao]);

        return $parecer->refresh();
    }

    /**
     * @throws ParecerDescritivoException
     */
    public function publicar(ParecerDescritivo $parecer, User $por): ParecerDescritivo
    {
        if ($parecer->estaPublicado()) {
            throw new ParecerDescritivoException('Este parecer já está publicado.');
        }

        $this->exigirTexto($parecer);

        if (str_contains((string) $parecer->texto, '[ALUNO]')) {
            throw new ParecerDescritivoException('O texto ainda tem o marcador [ALUNO]. Revise e substitua pelo nome do aluno.');
        }

        $parecer->update([
            'status' => StatusParecerDescritivo::Publicado,
            'publicado_por_user_id' => $por->id,
            'publicado_em' => now(),
        ]);

        return $parecer->refresh();
    }

    /**
     * @throws ParecerDescritivoException
     */
    public function reabrir(ParecerDescritivo $parecer, User $por): ParecerDescritivo
    {
        if (! $parecer->estaPublicado()) {
            throw new ParecerDescritivoException('Só um parecer publicado pode ser reaberto.');
        }

        $parecer->update([
            'status' => StatusParecerDescritivo::Rascunho,
            'publicado_por_user_id' => null,
            'publicado_em' => null,
        ]);

        return $parecer->refresh();
    }

    /**
     * @throws ParecerDescritivoException
     */
    private function exigirTexto(ParecerDescritivo $parecer): void
    {
        if (mb_strlen(trim((string) $parecer->texto)) < self::TAMANHO_MINIMO) {
            throw new ParecerDescritivoException('Escreva o parecer (pelo menos '.self::TAMANHO_MINIMO.' caracteres) antes de continuar.');
        }
    }
}
