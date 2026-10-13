<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico\Exceptions;

use RuntimeException;

class CopilotoIndisponivelException extends RuntimeException
{
    public function __construct(string $mensagem = 'O Copiloto Pedagógico está temporariamente indisponível. Aguarde alguns instantes e tente novamente.')
    {
        parent::__construct($mensagem);
    }
}
