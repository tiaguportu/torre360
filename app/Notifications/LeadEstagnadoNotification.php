<?php

namespace App\Notifications;

use App\Models\Interessado;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadEstagnadoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Interessado $interessado, public int $dias)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Atenção: Lead sem interação há '.$this->dias.' dias')
            ->greeting('Olá, '.$notifiable->name)
            ->line('O lead '.$this->interessado->pessoa->nome.' está há '.$this->dias.' dias sem qualquer contato registrado.')
            ->line('Considere entrar em contato para evitar a perda do lead.')
            ->action('Ver Interessado', url('/admin/interessados/'.$this->interessado->id.'/edit'))
            ->line('Por favor, realize o contato e atualize o sistema.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'interessado_id' => $this->interessado->id,
            'interessado_nome' => $this->interessado->pessoa->nome,
            'message' => 'O lead '.$this->interessado->pessoa->nome.' está há '.$this->dias.' dias sem interação.',
            'action_url' => '/admin/interessados/'.$this->interessado->id.'/edit',
        ];
    }
}
