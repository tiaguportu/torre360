<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AcompanhamentoInteressadoNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public \App\Models\Interessado $interessado)
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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Atenção: Acompanhamento de Interessado Pendente')
            ->greeting('Olá, ' . $notifiable->name)
            ->line('O interessado ' . $this->interessado->pessoa->nome . ' precisa de sua atenção.')
            ->line('A data agendada para o próximo contato está desatualizada ou já passou.')
            ->action('Ver Interessado', url('/admin/interessados/' . $this->interessado->id . '/edit'))
            ->line('Por favor, realize o contato e atualize o sistema.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'interessado_id' => $this->interessado->id,
            'interessado_nome' => $this->interessado->pessoa->nome,
            'message' => 'O interessado ' . $this->interessado->pessoa->nome . ' precisa de acompanhamento urgente.',
            'action_url' => '/admin/interessados/' . $this->interessado->id . '/edit',
        ];
    }
}
