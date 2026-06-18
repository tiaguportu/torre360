<?php

namespace App\Notifications;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use App\Models\Questionario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestionarioDisponivelNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Questionario $questionario) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = QuestionarioResource::getUrl('responder', ['record' => $this->questionario]);

        return (new MailMessage)
            ->subject('Questionário Disponível: '.$this->questionario->titulo)
            ->greeting('Olá, '.$notifiable->name)
            ->line('Gostaríamos de informar que o questionário "'.$this->questionario->titulo.'" está disponível para resposta.')
            ->line('Sua opinião é muito importante para nós. Por favor, dedique alguns minutos para respondê-lo.')
            ->action('Responder Questionário', $url)
            ->line('Se você já respondeu ou se este aviso não for aplicável, por favor desconsidere.');
    }
}
