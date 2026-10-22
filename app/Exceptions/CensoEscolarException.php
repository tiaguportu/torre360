<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Regra do Censo Escolar impedindo uma operação (gerar arquivo, importar retorno, ajustar situação). A mensagem é
 * escrita para a secretaria e pode ser mostrada na tela.
 */
class CensoEscolarException extends RuntimeException {}
