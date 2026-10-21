<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Uma regra do reajuste de mensalidades impediu a operação. A mensagem já está em português, pronta para a
 * notificação do painel.
 */
class ReajusteMensalidadeException extends DomainException {}
