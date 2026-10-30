<?php

namespace App\Exceptions;

use App\Models\Preceptoria;
use DomainException;

/**
 * A preceptoria não pode ser excluída. A mensagem é escrita para ser exibida ao usuário.
 */
class ExclusaoDePreceptoriaRecusadaException extends DomainException
{
    public static function comRelatorios(Preceptoria $preceptoria, int $relatorios): self
    {
        return new self(sprintf(
            'A preceptoria de %s às %s tem %d %s e não pode ser excluída. Para liberar o horário, tire o aluno dele.',
            $preceptoria->data->format('d/m/Y'),
            $preceptoria->hora_inicio->format('H:i'),
            $relatorios,
            $relatorios === 1 ? 'relatório' : 'relatórios',
        ));
    }
}
