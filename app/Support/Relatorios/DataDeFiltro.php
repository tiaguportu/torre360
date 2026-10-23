<?php

declare(strict_types=1);

namespace App\Support\Relatorios;

/**
 * Data de um filtro de relatório (Onda 72): só vale a data de calendário inteira (AAAA-MM-DD, com hora opcional), sem sobra
 * de texto. O valor chega de um formulário ou de um relatório salvo há tempo e vai para uma consulta.
 */
final class DataDeFiltro
{
    public static function normalizar(mixed $valor): ?string
    {
        if (! is_string($valor) || preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: \d{2}:\d{2}(?::\d{2})?)?$/', trim($valor), $partes) !== 1) {
            return null;
        }

        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]) ? "{$partes[1]}-{$partes[2]}-{$partes[3]}" : null;
    }
}
