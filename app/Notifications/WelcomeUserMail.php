<?php

namespace App\Notifications;

use App\Support\LinkDefinirSenha;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Boas-vindas a um usuário recém-criado. Não leva senha: leva um link para a própria pessoa definir a dela.
 */
class WelcomeUserMail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bem-vindo ao Torre360')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Sua conta foi criada com sucesso no sistema Torre360.')
            ->line('**Login (e-mail):** '.$notifiable->email)
            ->line('Para acessar, defina a sua senha pelo botão abaixo. O link vale por '.LinkDefinirSenha::validadeEmMinutos().' minutos.')
            ->action('Definir minha senha', LinkDefinirSenha::para($notifiable))
            ->line('Se o link expirar, abra a tela de acesso e use a opção "Esqueci minha senha" com este mesmo e-mail.')
            ->line('Se você não esperava esta mensagem, ignore este e-mail.')
            ->salutation('Atenciosamente, '.config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
