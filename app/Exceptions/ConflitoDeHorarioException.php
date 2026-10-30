<?php

namespace App\Exceptions;

use App\Models\Preceptoria;
use DomainException;

/**
 * O professor ou o aluno já tem outra preceptoria num horário que se sobrepõe ao pedido.
 * A mensagem é escrita para ser exibida ao usuário.
 */
class ConflitoDeHorarioException extends DomainException
{
    public static function doProfessor(Preceptoria $outra): self
    {
        return new self(sprintf(
            'Conflito: %s já tem uma preceptoria em %s das %s às %s.',
            $outra->professor?->nome ?? 'o professor',
            $outra->data->format('d/m/Y'),
            $outra->hora_inicio->format('H:i'),
            $outra->hora_fim?->format('H:i') ?? 'fim não informado',
        ));
    }

    public static function doAluno(Preceptoria $outra): self
    {
        return new self(sprintf(
            'Conflito: %s já tem uma preceptoria em %s das %s às %s%s.',
            $outra->matricula?->pessoa?->nome ?? 'o aluno',
            $outra->data->format('d/m/Y'),
            $outra->hora_inicio->format('H:i'),
            $outra->hora_fim?->format('H:i') ?? 'fim não informado',
            $outra->professor ? " (com {$outra->professor->nome})" : '',
        ));
    }
}
