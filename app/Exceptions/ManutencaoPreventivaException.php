<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Uma regra da manutenção preventiva impediu a operação. A mensagem já está em português, pronta para a notificação.
 */
class ManutencaoPreventivaException extends DomainException {}
