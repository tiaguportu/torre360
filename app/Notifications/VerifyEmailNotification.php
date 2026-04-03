<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifique seu endereço de e-mail - '.config('app.name'))
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Para começar a usar todos os recursos do sistema Torre360, precisamos que você confirme seu endereço de e-mail.')
            ->line('Clique no botão abaixo para realizar a verificação.')
            ->action('Verificar Endereço de E-mail', $verificationUrl)
            ->line('Este link de verificação expirará em '.config('auth.verification.expire', 60).' minutos para sua segurança.')
            ->line('Se você não criou uma conta em nosso sistema, nenhuma ação adicional é necessária.')
            ->salutation('Atenciosamente, '.config('app.name'));
    }

    /**
     * Sobrescreve a URL original para apontar para a rota de verificação do Filament.
     */
    protected function verificationUrl($notifiable)
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        return URL::temporarySignedRoute(
            'filament.admin.auth.email-verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
