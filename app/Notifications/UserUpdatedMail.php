<?php

namespace App\Notifications;

use App\Support\LinkDefinirSenha;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de alteração de dados de usuário. Quando a senha foi trocada por um administrador, o e-mail avisa e
 * oferece um link para o usuário definir uma nova — a senha em si nunca é enviada.
 */
class UserUpdatedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $senhaAlterada = false)
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

        if ($this->senhaAlterada) {
            return $message
                ->line('A sua senha foi redefinida por um administrador. Defina uma nova senha pelo botão abaixo (o link vale por '.LinkDefinirSenha::validadeEmMinutos().' minutos).')
                ->action('Definir minha senha', LinkDefinirSenha::para($notifiable))
                ->line('Se você não reconhece esta alteração, avise a secretaria da escola.')
                ->salutation('Atenciosamente, '.config('app.name'));
        }

        return $message
            ->action('Acessar o Painel', url('/'))
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
