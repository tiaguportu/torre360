<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Uma regra da avaliação de desempenho docente impediu a operação. A mensagem já está em português, pronta para a
 * notificação do painel.
 */
class AvaliacaoDocenteException extends DomainException {}
