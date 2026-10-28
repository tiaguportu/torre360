<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\StatusFatura;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class FaturaImportParser
{
    /**
     * Parser numérico tolerante para valores monetários.
     * Aceita: 1234.56, 1234,56, 1.234,56, R$ 1.234,56, etc.
     * Rejeita valores negativos e formatos inválidos.
     */
    public static function parseMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value >= 0 ? (float) $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $str = trim($value);

        // Remove caracteres invisíveis / non-breaking space (U+00A0)
        $str = preg_replace('/[\x{00A0}\x{200E}\x{200F}]/u', ' ', $str);
        $str = trim((string) $str);

        // Remove prefixo de moeda R$ (case-insensitive)
        $str = preg_replace('/^r\$\s*/i', '', $str);
        $str = trim((string) $str);

        if ($str === '') {
            return null;
        }

        // Valida se contém apenas números, sinais, pontos e vírgulas
        if (! preg_match('/^[+-]?[0-9.,]+$/', $str)) {
            return null;
        }

        $hasComma = str_contains($str, ',');
        $hasDot = str_contains($str, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($str, ',');
            $lastDot = strrpos($str, '.');

            if ($lastComma > $lastDot) {
                // Formato brasileiro: 1.234,56
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                // Formato internacional: 1,234.56
                $str = str_replace(',', '', $str);
            }
        } elseif ($hasComma) {
            if (substr_count($str, ',') > 1) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        } elseif ($hasDot) {
            if (substr_count($str, '.') > 1) {
                $str = str_replace('.', '', $str);
            }
        }

        if (! is_numeric($str)) {
            return null;
        }

        $float = (float) $str;

        return $float >= 0 ? $float : null;
    }

    /**
     * Parser estrito de data: aceita d/m/Y e Y-m-d.
     * Rejeita datas inválidas como 31/02/2026.
     * Retorna a data no formato Y-m-d para armazenamento no banco de dados.
     */
    public static function parseStrictDate(mixed $value): ?string
    {
        if (empty($value) || ! is_string($value)) {
            return null;
        }

        $str = trim($value);

        // 1. Tenta formato d/m/Y (estrito)
        $dmy = \DateTimeImmutable::createFromFormat('!d/m/Y', $str);
        if ($dmy && $dmy->format('d/m/Y') === $str) {
            return $dmy->format('Y-m-d');
        }

        // 2. Tenta formato Y-m-d (estrito)
        $ymd = \DateTimeImmutable::createFromFormat('!Y-m-d', $str);
        if ($ymd && $ymd->format('Y-m-d') === $str) {
            return $ymd->format('Y-m-d');
        }

        return null;
    }

    /**
     * Parser de tipo de desconto.
     * Aceita: R$, absoluto -> 'absoluto'
     * Aceita: %, relativo, percentual -> 'relativo'
     * Padrão (se vazio): 'absoluto'
     */
    public static function parseTipoDesconto(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return 'absoluto';
        }

        $str = mb_strtolower(trim((string) $value));

        if (in_array($str, ['r$', 'absoluto'], true)) {
            return 'absoluto';
        }

        if (in_array($str, ['%', 'relativo', 'percentual'], true)) {
            return 'relativo';
        }

        return null;
    }

    /**
     * Parser tolerante de status para importação de faturas.
     * Aceita todos os valores e rótulos do enum StatusFatura (inclusive 'pago' e 'parcial').
     * Retorna null se vazio.
     * Lança RowImportFailedException apenas se o valor informado for inválido/desconhecido.
     */
    public static function parseStatusTolerante(mixed $value): ?StatusFatura
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof StatusFatura) {
            return $value;
        }

        $str = mb_strtolower(trim((string) $value));

        return match ($str) {
            'pendente' => StatusFatura::Pendente,
            'pago' => StatusFatura::Pago,
            'atrasado' => StatusFatura::Atrasado,
            'cancelado' => StatusFatura::Cancelado,
            'parcial', 'pago parcialmente' => StatusFatura::Parcial,
            default => throw new RowImportFailedException("Status '{$value}' inválido. Valores aceitos: pendente, atrasado, cancelado."),
        };
    }

    /**
     * Parser de status para importação de faturas.
     * Apenas 'pendente', 'atrasado' e 'cancelado' são permitidos.
     * 'pago' e 'parcial' são recusados com RowImportFailedException.
     * Padrão (se vazio): StatusFatura::Pendente.
     */
    public static function parseStatus(mixed $value): StatusFatura
    {
        if ($value === null || $value === '') {
            return StatusFatura::Pendente;
        }

        if ($value instanceof StatusFatura) {
            if (in_array($value, [StatusFatura::Pendente, StatusFatura::Atrasado, StatusFatura::Cancelado], true)) {
                return $value;
            }

            throw new RowImportFailedException("O status '{$value->getLabel()}' não é permitido na importação. Faturas pagas ou parciais devem ser baixadas via transações bancárias.");
        }

        $str = mb_strtolower(trim((string) $value));

        if (in_array($str, ['pago', 'parcial', 'pago parcialmente'], true)) {
            throw new RowImportFailedException("O status '{$value}' não é permitido na importação. Faturas pagas ou parciais devem ser baixadas via transações bancárias.");
        }

        return match ($str) {
            'pendente' => StatusFatura::Pendente,
            'atrasado' => StatusFatura::Atrasado,
            'cancelado' => StatusFatura::Cancelado,
            default => throw new RowImportFailedException("Status '{$value}' inválido. Valores aceitos: pendente, atrasado, cancelado."),
        };
    }
}
