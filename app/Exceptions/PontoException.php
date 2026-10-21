<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Regra de negócio do ponto eletrônico violada. A mensagem é escrita para quem usa o sistema.
 */
class PontoException extends RuntimeException {}
