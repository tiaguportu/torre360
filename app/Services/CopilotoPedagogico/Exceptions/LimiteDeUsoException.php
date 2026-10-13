<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico\Exceptions;

use RuntimeException;

class LimiteDeUsoException extends RuntimeException
{
    public function __construct(string $mensagem = 'Você atingiu o limite de consultas por minuto com o Copiloto Pedagógico. Aguarde um instante antes de solicitar nova geração.')
    {
        parent::__construct($mensagem);
    }
}
