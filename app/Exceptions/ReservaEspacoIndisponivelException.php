<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * A reserva não pode ser feita, alterada, confirmada ou cancelada. As mensagens já estão em português, prontas
 * para ir à notificação do painel.
 */
class ReservaEspacoIndisponivelException extends DomainException
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
