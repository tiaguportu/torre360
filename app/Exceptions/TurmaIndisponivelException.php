<?php

namespace App\Exceptions;

use App\Models\Turma;
use DomainException;

/**
 * A turma não pode receber a matrícula (lotada, fora do período ou fechada para matrículas).
 * A mensagem é escrita para ser exibida ao usuário.
 */
class TurmaIndisponivelException extends DomainException
{
    private bool $turmaSemVaga = false;

    public static function lotada(Turma $turma, int $limite): self
    {
        $erro = new self("A turma '{$turma->nome}' atingiu a lotação máxima de {$limite} vagas.");
        $erro->turmaSemVaga = true;

        return $erro;
    }

    public static function semVagasSuficientes(Turma $turma, int $disponiveis, int $solicitadas): self
    {
        $erro = new self("A turma \"{$turma->nome}\" possui apenas {$disponiveis} vaga(s) disponível(is) e você tentou matricular {$solicitadas} aluno(s).");
        $erro->turmaSemVaga = true;

        return $erro;
    }

    public static function fechada(Turma $turma): self
    {
        $status = $turma->status?->getLabel() ?? 'sem status';

        $erro = new self("A turma '{$turma->nome}' não está aberta para matrículas (status: {$status}).");
        $erro->turmaSemVaga = true;

        return $erro;
    }

    public static function periodoDiferente(Turma $turma): self
    {
        return new self("A turma '{$turma->nome}' não pertence ao período letivo de destino da rematrícula.");
    }

    public static function serieDiferente(Turma $turma): self
    {
        return new self("A turma '{$turma->nome}' não é da série pretendida pela família.");
    }

    /**
     * A turma não aceita mais ninguém (lotada ou fechada): numa operação em lote, as próximas
     * matrículas na mesma turma falhariam igual, então o lote deve parar.
     */
    public function turmaSemVaga(): bool
    {
        return $this->turmaSemVaga;
    }
}
