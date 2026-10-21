<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico\Exceptions;

use RuntimeException;

class ParecerIaDesabilitadoException extends RuntimeException
{
    public function __construct(string $mensagem = 'O rascunho de parecer com IA está desligado até a escola homologar o plano corporativo com o provedor de IA (conformidade LGPD). Escreva o parecer manualmente ou fale com a direção.')
    {
        parent::__construct($mensagem);
    }
}
