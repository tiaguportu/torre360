<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserMail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $password)
    {
        //
    }

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
            ->greeting('Olá, ' . $notifiable->name . '!')
            ->line('Sua conta foi criada com sucesso no sistema Torre360.')
            ->line('Abaixo estão suas credenciais de acesso:')
            ->line('**Login (E-mail):** ' . $notifiable->email)
            ->line('**Senha:** ' . $this->password)
            ->action('Acessar o Painel', url('/admin'))
            ->line('Recomendamos que você altere sua senha após o primeiro acesso.')
            ->line('Se você não solicitou esta conta, ignore este e-mail.')
            ->salutation('Atenciosamente, ' . config('app.name'));
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
