<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;

/**
 * Link de definição de senha (mesma tela e mesmo token de "Esqueci minha senha").
 *
 * Substitui o envio de senha por e-mail: o usuário escolhe a própria senha e nenhum segredo fica em texto
 * claro na fila (`jobs`/`failed_jobs`), no log de e-mails nem na caixa de entrada. O token é gerado só no
 * momento do envio (dentro do `toMail`, já no worker), nunca serializado na notificação enfileirada.
 */
class LinkDefinirSenha
{
    public static function para(User $user): string
    {
        $token = Password::broker(Filament::getAuthPasswordBroker())->createToken($user);

        return Filament::getResetPasswordUrl($token, $user);
    }

    /**
     * Minutos de validade do link, conforme o broker de senhas do painel.
     */
    public static function validadeEmMinutos(): int
    {
        return (int) config('auth.passwords.'.(Filament::getAuthPasswordBroker() ?? config('auth.defaults.passwords')).'.expire', 60);
    }
}
