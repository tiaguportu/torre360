<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sanitizador defensivo contra injeção de fórmulas em arquivos CSV/TSV
 * (OWASP Top 10 A03:2021 / CWE-1236: CSV Formula Injection).
 *
 * Neutraliza caracteres de gatilho de execução (=, +, -, @, \t, \r, |, %)
 * prefixando valores textuais sensíveis com apóstrofo ('), forçando
 * softwares de planilha (Microsoft Excel, LibreOffice Calc, Google Sheets)
 * a interpretar o conteúdo estritamente como texto literal.
 */
class CsvSanitizer
{
    /**
     * Caracteres de disparo de fórmulas e comandos DDE em planilhas.
     *
     * @var list<string>
     */
    private const TRIGGER_CHARACTERS = ['=', '@', '|', '%', "\t", "\r"];

    /**
     * Sanitiza um valor individual para exportação segura em CSV.
     */
    public static function sanitize(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);

        if ($trimmed === '') {
            return $value;
        }

        $firstChar = $trimmed[0];

        // 1. Caracteres que sempre caracterizam gatilho de fórmula ou evasão por controle
        if (in_array($firstChar, self::TRIGGER_CHARACTERS, true)) {
            return "'".$value;
        }

        // 2. Operadores aritméticos (+ e -): seguros apenas se forem puramente numéricos (ex: -10, +5, -3.14)
        if (in_array($firstChar, ['+', '-'], true)) {
            if (! is_numeric($trimmed)) {
                return "'".$value;
            }
        }

        return $value;
    }

    /**
     * Sanitiza uma linha inteira de dados (array de colunas).
     *
     * @param  array<mixed>  $row
     * @return array<mixed>
     */
    public static function sanitizeRow(array $row): array
    {
        return array_map([static::class, 'sanitize'], $row);
    }

    /**
     * Wrapper seguro para a função nativa fputcsv, aplicando sanitização defensiva
     * automática em todas as colunas antes de gravar no handle do arquivo.
     *
     * @param  resource  $handle
     * @param  array<mixed>  $fields
     */
    public static function fputcsv(
        $handle,
        array $fields,
        string $separator = ';',
        string $enclosure = '"',
        string $escape = '\\',
        string $eol = "\n"
    ): int|false {
        $sanitized = static::sanitizeRow($fields);

        return \fputcsv($handle, $sanitized, $separator, $enclosure, $escape, $eol);
    }

    /**
     * Desfaz a sanitização defensiva ao reimportar dados de um CSV para a base de dados.
     */
    public static function desanitize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (str_starts_with($value, "'") && strlen($value) > 1) {
            $nextChar = ltrim(substr($value, 1))[0] ?? '';
            if (in_array($nextChar, ['=', '+', '-', '@', '|', '%', "\t", "\r"], true)) {
                return substr($value, 1);
            }
        }

        return $value;
    }
}
