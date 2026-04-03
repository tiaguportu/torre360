<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserUpdatedMail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ?string $password = null)
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
        $message = (new MailMessage)
            ->subject('Alteração de Dados no Torre360')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Suas informações de usuário foram atualizadas no sistema Torre360.');

        if ($this->password) {
            $message->line('Sua nova senha é: **'.$this->password.'**');
        }

        return $message
            ->action('Acessar o Painel', url('/'))
            ->line('Recomendamos que você altere sua senha após o acesso, caso ela tenha sido modificada.')
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
