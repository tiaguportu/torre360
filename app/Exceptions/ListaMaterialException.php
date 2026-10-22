<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Regra da lista de material escolar impedindo uma operação (item de uso coletivo, lista já publicada, publicação
 * incompleta). A mensagem é escrita para a secretaria e pode ser mostrada na tela.
 */
class ListaMaterialException extends RuntimeException {}
