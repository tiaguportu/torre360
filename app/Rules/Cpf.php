<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida um CPF pelos dígitos verificadores (aceita com ou sem máscara).
 */
class Cpf implements ValidationRule
{
    public static function valido(?string $valor): bool
    {
        $cpf = preg_replace('/\D/', '', (string) $valor);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }

            $digito = ((10 * $soma) % 11) % 10;

            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valido(is_string($value) ? $value : null)) {
            $fail('O :attribute informado não é um CPF válido.');
        }
    }
}
