<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico\Exceptions;

use RuntimeException;

class PeiDesabilitadoException extends RuntimeException
{
    public function __construct(string $mensagem = 'O Copiloto de Inclusão e PEI está temporariamente bloqueado por conformidade LGPD até homologação do plano corporativo com o provedor de IA. Entre em contato com a direção.')
    {
        parent::__construct($mensagem);
    }
}
