<?php

namespace App\Notifications\Preceptorias;

use App\Models\Preceptoria;
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
        public string $tipo // 'agendamento' ou 'liberacao'
    ) {}

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
        $dataF = $this->preceptoria->data ? $this->preceptoria->data->format('d/m/Y') : 'N/D';
        $horaF = $this->preceptoria->hora_inicio ? $this->preceptoria->hora_inicio->format('H:i') : 'N/D';
        $aluno = $this->preceptoria->matricula?->pessoa?->nome ?? 'N/D';

        if ($this->tipo === 'agendamento') {
            return (new MailMessage)
                ->subject('Novo Agendamento de Preceptoria')
                ->greeting("Olá, {$notifiable->name}!")
                ->line('Uma nova preceptoria foi agendada para você.')
                ->line("Aluno: {$aluno}")
                ->line("Data: {$dataF}")
                ->line("Horário: {$horaF}")
                ->action('Ver Preceptorias', url('/admin/preceptorias'))
                ->line('Obrigado por utilizar nosso sistema!');
        }

        return (new MailMessage)
            ->subject('Preceptoria Cancelada/Liberada')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Um horário de preceptoria anteriormente agendado foi liberado.')
            ->line("Aluno (era): {$aluno}")
            ->line("Data: {$dataF}")
            ->line("Horário: {$horaF}")
            ->action('Ver Disponibilidade', url('/admin/preceptorias'))
            ->line('Obrigado por utilizar nosso sistema!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $dataF = $this->preceptoria->data ? $this->preceptoria->data->format('d/m/Y') : 'N/D';
        $horaF = $this->preceptoria->hora_inicio ? $this->preceptoria->hora_inicio->format('H:i') : 'N/D';
        $aluno = $this->preceptoria->matricula?->pessoa?->nome ?? 'N/A';

        if ($this->tipo === 'agendamento') {
            return [
                'type' => 'preceptoria_agendada',
                'title' => 'Nova Preceptoria Agendada',
                'message' => "Preceptoria agendada com {$aluno} para {$dataF} às {$horaF}.",
                'preceptoria_id' => $this->preceptoria->id,
                'url' => '/admin/preceptorias',
            ];
        }

        return [
            'type' => 'preceptoria_liberada',
            'title' => 'Preceptoria Liberada',
            'message' => "O horário de {$dataF} às {$horaF} foi liberado.",
            'preceptoria_id' => $this->preceptoria->id,
            'url' => '/admin/preceptorias',
        ];
    }
}
