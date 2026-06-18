<?php

namespace App\Notifications;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use App\Models\Questionario;
use App\Notifications\Channels\FcmChannel;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
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
        return ['mail', 'database', FcmChannel::class];
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

    public function toDatabase(object $notifiable): array
    {
        $url = QuestionarioResource::getUrl('responder', ['record' => $this->questionario]);

        $notification = FilamentNotification::make()
            ->title('Questionário Disponível')
            ->body('O questionário "'.$this->questionario->titulo.'" está disponível para resposta.')
            ->info();

        if ($url) {
            $notification->actions([
                FilamentAction::make('view')
                    ->label('Responder')
                    ->url($url)
                    ->button(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }

    public function toPush(object $notifiable): array
    {
        $url = QuestionarioResource::getUrl('responder', ['record' => $this->questionario]);

        return [
            'title' => 'Questionário Disponível',
            'body' => 'O questionário "'.$this->questionario->titulo.'" está disponível para resposta.',
            'data' => [
                'url' => $url,
                'type' => 'questionario',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        $url = QuestionarioResource::getUrl('responder', ['record' => $this->questionario]);

        return [
            'title' => 'Questionário Disponível',
            'body' => 'O questionário "'.$this->questionario->titulo.'" está disponível para resposta.',
            'action_url' => $url,
            'type' => 'questionario',
        ];
    }
}
