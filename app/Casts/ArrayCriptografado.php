<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Array guardado como JSON criptografado (`Crypt`, com a `APP_KEY`), para dados pessoais de terceiros que o
 * banco não precisa ler (ex.: rascunho de pré-matrícula com CPF e endereço da família).
 *
 * Na leitura, texto que não decifra é tratado como JSON puro: é como os valores eram gravados antes da
 * criptografia, e assim o código pode ir ao ar antes da migration que cifra os registros antigos.
 *
 * @implements CastsAttributes<array<mixed>|null, array<mixed>|null>
 */
class ArrayCriptografado implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $json = Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            $json = (string) $value;
        }

        $dados = json_decode($json, true);

        return is_array($dados) ? $dados : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
