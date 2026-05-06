<?php

namespace App\Notifications\Preceptorias;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\Preceptoria;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LembretePreceptoriaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Preceptoria $preceptoria
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        // Sininho (database) e Push (FCM) apenas para usuários
        if ($notifiable instanceof User) {
            $channels[] = 'database';
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $dataF = $this->preceptoria->data ? $this->preceptoria->data->format('d/m/Y') : 'N/D';
        $horaF = $this->preceptoria->hora_inicio ? $this->preceptoria->hora_inicio->format('H:i') : 'N/D';
        $aluno = $this->preceptoria->matricula?->pessoa?->nome ?? 'N/D';
        $professor = $this->preceptoria->professor?->nome ?? 'N/D';

        $nome = $notifiable instanceof User ? $notifiable->name : $notifiable->nome;

        return (new MailMessage)
            ->subject('Lembrete de Agendamento de Preceptoria')
            ->greeting("Olá, {$nome}!")
            ->line('Este é um lembrete de que você possui uma preceptoria agendada.')
            ->line("Aluno: {$aluno}")
            ->line("Professor: {$professor}")
            ->line("Data: {$dataF}")
            ->line("Horário: {$horaF}")
            ->action('Ver Preceptoria', PreceptoriaResource::getUrl('view', ['record' => $this->preceptoria]))
            ->line('Contamos com a sua presença!');
    }

    /**
     * Get the database representation of the notification (Filament).
     */
    public function toDatabase(object $notifiable): array
    {
        $dataF = $this->preceptoria->data ? $this->preceptoria->data->format('d/m/Y') : 'N/D';
        $horaF = $this->preceptoria->hora_inicio ? $this->preceptoria->hora_inicio->format('H:i') : 'N/D';
        $aluno = $this->preceptoria->matricula?->pessoa?->nome ?? 'N/A';
        $professor = $this->preceptoria->professor?->nome ?? 'N/D';

        return FilamentNotification::make()
            ->title('Lembrete de Preceptoria')
            ->body("Lembrete: Preceptoria entre {$aluno} e {$professor} agendada para {$dataF} às {$horaF}.")
            ->actions([
                Action::make('view')
                    ->label('Ver Preceptoria')
                    ->url(PreceptoriaResource::getUrl('view', ['record' => $this->preceptoria]))
                    ->button(),
            ])
            ->getDatabaseMessage();
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toPush(object $notifiable): array
    {
        $dataF = $this->preceptoria->data ? $this->preceptoria->data->format('d/m/Y') : 'N/D';
        $horaF = $this->preceptoria->hora_inicio ? $this->preceptoria->hora_inicio->format('H:i') : 'N/D';
        $aluno = $this->preceptoria->matricula?->pessoa?->nome ?? 'N/A';
        $professor = $this->preceptoria->professor?->nome ?? 'N/D';

        return [
            'title' => 'Lembrete de Preceptoria',
            'body' => "Lembrete: Preceptoria em {$dataF} às {$horaF} ({$aluno} e {$professor}).",
            'data' => [
                'url' => PreceptoriaResource::getUrl('view', ['record' => $this->preceptoria], absolute: false),
                'preceptoria_id' => $this->preceptoria->id,
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'preceptoria_id' => $this->preceptoria->id,
        ];
    }
}
