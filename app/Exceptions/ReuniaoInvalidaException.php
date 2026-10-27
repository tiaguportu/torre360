<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * A reunião, a ata ou o encaminhamento não aceita a operação pedida (reunião já cancelada, ata já fechada, prazo no passado,
 * conselho com aluno sem deliberação). As mensagens já estão em português, prontas para ir à notificação do painel.
 */
class ReuniaoInvalidaException extends DomainException
{
    /**
     * @param  list<string>  $mensagens
     */
    public function __construct(private readonly array $mensagens)
    {
        parent::__construct(implode(' ', $mensagens));
    }

    public static function com(string $mensagem): self
    {
        return new self([$mensagem]);
    }

    /**
     * @return list<string>
     */
    public function mensagens(): array
    {
        return $this->mensagens;
    }
}
