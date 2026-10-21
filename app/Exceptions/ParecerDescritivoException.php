<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Uma regra do parecer descritivo impediu a operação. A mensagem já está em português, pronta para a notificação.
 */
class ParecerDescritivoException extends DomainException {}
