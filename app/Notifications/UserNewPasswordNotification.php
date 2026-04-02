<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserNewPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $password) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nova Senha Gerada - Torre360')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Você solicitou o reset da sua senha no sistema Torre360.')
            ->line('Geramos uma nova senha segura para você:')
            ->line('**Sua nova senha:** '.$this->password)
            ->action('Acessar o Sistema', url('/admin/login'))
            ->line('Para sua segurança, recomendamos que você altere sua senha assim que fizer o login.')
            ->line('Se você não solicitou o reset de senha, ignore este e-mail.')
            ->salutation('Atenciosamente, '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
