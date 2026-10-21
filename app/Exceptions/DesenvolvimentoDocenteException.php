<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Uma regra da autoavaliação ou do plano de desenvolvimento docente impediu a operação. A mensagem já está em
 * português, pronta para a notificação do painel.
 */
class DesenvolvimentoDocenteException extends DomainException {}
