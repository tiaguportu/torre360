<?php

namespace App\Notifications\Preceptorias;

use App\Models\Preceptoria;
use App\Notifications\Channels\FcmChannel;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreceptoriaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Preceptoria $preceptoria,
        public string $tipo, // 'agendamento' ou 'liberacao'
        public bool $paraSolicitante = false
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
        $dataF = $this->preceptoria->data ? $this->preceptoria->data->format('d/m/Y') : 'N/D';
        $horaF = $this->preceptoria->hora_inicio ? $this->preceptoria->hora_inicio->format('H:i') : 'N/D';
        $aluno = $this->preceptoria->matricula?->pessoa?->nome ?? 'N/D';
        $professor = $this->preceptoria->professor?->nome ?? 'N/D';

        if ($this->tipo === 'agendamento') {
            $intro = $this->paraSolicitante
                ? 'Você agendou com sucesso uma preceptoria.'
                : 'Uma nova preceptoria foi agendada para você.';

            return (new MailMessage)
                ->subject('Agendamento de Preceptoria')
                ->greeting("Olá, {$notifiable->name}!")
                ->line($intro)
                ->line("Aluno: {$aluno}")
                ->line("Professor: {$professor}")
                ->line("Data: {$dataF}")
                ->line("Horário: {$horaF}")
                ->action('Ver Preceptorias', url('/admin/preceptorias'));
        }

        $intro = $this->paraSolicitante
            ? 'Você cancelou/liberou um horário de preceptoria.'
            : 'Um horário de preceptoria anteriormente agendado foi liberado.';

        return (new MailMessage)
            ->subject('Preceptoria Cancelada/Liberada')
            ->greeting("Olá, {$notifiable->name}!")
            ->line($intro)
            ->line("Aluno (era): {$aluno}")
            ->line("Professor: {$professor}")
            ->line("Data: {$dataF}")
            ->line("Horário: {$horaF}")
            ->action('Ver Disponibilidade', url('/admin/preceptorias'));
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

        $title = $this->tipo === 'agendamento'
            ? ($this->paraSolicitante ? 'Preceptoria Agendada' : 'Nova Preceptoria Agendada')
            : ($this->paraSolicitante ? 'Horário Liberado' : 'Preceptoria Liberada');

        $body = $this->tipo === 'agendamento'
            ? ($this->paraSolicitante
                ? "Você agendou com {$professor} para {$dataF} às {$horaF}."
                : "Preceptoria agendada com {$aluno} para {$dataF} às {$horaF}.")
            : ($this->paraSolicitante
                ? "Você liberou o horário de {$dataF} às {$horaF}."
                : "O horário de {$dataF} às {$horaF} foi liberado.");

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
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

        $title = $this->tipo === 'agendamento'
            ? ($this->paraSolicitante ? 'Preceptoria Agendada' : 'Nova Preceptoria Agendada')
            : ($this->paraSolicitante ? 'Horário Liberado' : 'Preceptoria Liberada');

        $body = $this->tipo === 'agendamento'
            ? ($this->paraSolicitante
                ? "Você agendou com {$professor} para {$dataF} às {$horaF}."
                : "Preceptoria agendada com {$aluno} para {$dataF} às {$horaF}.")
            : ($this->paraSolicitante
                ? "Você liberou o horário de {$dataF} às {$horaF}."
                : "O horário de {$dataF} às {$horaF} foi liberado.");

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'url' => '/admin/preceptorias',
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
            'tipo' => $this->tipo,
            'para_solicitante' => $this->paraSolicitante,
        ];
    }
}
