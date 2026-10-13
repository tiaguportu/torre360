<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico\Exceptions;

use RuntimeException;

class RespostaIaInvalidaException extends RuntimeException
{
    public function __construct(string $mensagem = 'A resposta gerada pela IA não atende aos critérios curriculares da BNCC ou possui formato inválido. Tente novamente.')
    {
        parent::__construct($mensagem);
    }
}
