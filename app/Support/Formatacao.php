<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formatação de documentos e valores para telas e PDFs.
 */
final class Formatacao
{
    /**
     * CPF no formato 000.000.000-00. Valor que não tem 11 dígitos volta como veio (ou nulo se vazio).
     */
    public static function cpf(?string $cpf): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $cpf);

        return strlen((string) $digitos) === 11
            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digitos)
            : ($cpf ?: null);
    }

    /**
     * CNPJ no formato 00.000.000/0000-00, com a mesma regra do CPF.
     */
    public static function cnpj(?string $cnpj): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $cnpj);

        return strlen((string) $digitos) === 14
            ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digitos)
            : ($cnpj ?: null);
    }

    public static function reais(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
