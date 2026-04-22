<?php

namespace App\Notifications\Preceptorias;

use App\Models\Matricula;
use App\Notifications\Channels\FcmChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PossibilidadePreceptoriaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Matricula $matricula
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', FcmChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $aluno = $this->matricula->pessoa?->nome ?? 'N/D';

        return (new MailMessage)
            ->subject('Possibilidade de Agendamento de Preceptoria')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Gostaríamos de informar que existem horários disponíveis para agendamento de preceptoria para a matrícula de **{$aluno}**.")
            ->line('A preceptoria é um momento importante para o acompanhamento pedagógico e desenvolvimento do aluno.')
            ->action('Agendar Agora', url('/admin/preceptorias/agendar'))
            ->line('Acesse o painel para escolher o melhor horário disponível.');
    }

    /**
     * Get the database representation of the notification (Filament).
     */
    public function toDatabase(object $notifiable): array
    {
        $aluno = $this->matricula->pessoa?->nome ?? 'Aluno';

        return FilamentNotification::make()
            ->title('Possibilidade de Preceptoria')
            ->body("Existem novos horários disponíveis para agendar a preceptoria de **{$aluno}**.")
            ->actions([
                Action::make('agendar')
                    ->label('Agendar')
                    ->url('/admin/preceptorias')
                    ->button(),
            ])
            ->info()
            ->getDatabaseMessage();
    }

    /**
     * Get the push representation of the notification (FCM).
     */
    public function toPush(object $notifiable): array
    {
        $aluno = $this->matricula->pessoa?->nome ?? 'Aluno';

        return [
            'title' => 'Possibilidade de Preceptoria',
            'body' => "Novos horários disponíveis para agendar a preceptoria de {$aluno}.",
            'data' => [
                'url' => '/admin/preceptorias',
                'matricula_id' => $this->matricula->id,
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'matricula_id' => $this->matricula->id,
        ];
    }
}
