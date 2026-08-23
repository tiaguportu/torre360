<?php

namespace App\Notifications;

use App\Models\OcorrenciaEscolar;
use App\Notifications\Channels\FcmChannel;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OcorrenciaRegistradaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(protected OcorrenciaEscolar $ocorrencia) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $aluno = $this->ocorrencia->matricula?->pessoa?->nome ?? 'Aluno';
        $tipo = $this->ocorrencia->tipoOcorrencia?->nome ?? 'Ocorrência';
        $dataHora = optional($this->ocorrencia->data_hora)->format('d/m/Y H:i') ?? 'N/A';
        $descricao = $this->ocorrencia->descricao;

        return (new MailMessage)
            ->subject("Registro de Ocorrência Escolar - {$aluno}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Uma nova ocorrência escolar foi registrada para o aluno **{$aluno}**.")
            ->line("**Tipo de Ocorrência:** {$tipo}")
            ->line("**Data/Hora:** {$dataHora}")
            ->line("**Relato:** {$descricao}")
            ->when($this->ocorrencia->providencias_tomadas, function ($mail) {
                return $mail->line("**Providências Tomadas:** {$this->ocorrencia->providencias_tomadas}");
            })
            ->line('Permanecemos à disposição para eventuais esclarecimentos.')
            ->salutation('Atenciosamente, Equipe Pedagógica / Torre 360');
    }

    public function toDatabase(object $notifiable): array
    {
        $aluno = $this->ocorrencia->matricula?->pessoa?->nome ?? 'Aluno';
        $tipo = $this->ocorrencia->tipoOcorrencia?->nome ?? 'Ocorrência';
        $dataHora = optional($this->ocorrencia->data_hora)->format('d/m/Y H:i');

        $notification = FilamentNotification::make()
            ->title("Ocorrência Escolar: {$aluno}")
            ->body("{$tipo} registrado em {$dataHora}.")
            ->info();

        $data = $notification->getDatabaseMessage();
        $data['ocorrencia_id'] = $this->ocorrencia->id;

        return $data;
    }

    public function toPush(object $notifiable): array
    {
        $aluno = $this->ocorrencia->matricula?->pessoa?->nome ?? 'Aluno';
        $tipo = $this->ocorrencia->tipoOcorrencia?->nome ?? 'Ocorrência';

        return [
            'title' => "Ocorrência Escolar - {$aluno}",
            'body' => "{$tipo}: {$this->ocorrencia->descricao}",
            'data' => [
                'type' => 'ocorrencia_escolar',
                'ocorrencia_id' => (string) $this->ocorrencia->id,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ocorrencia_id' => $this->ocorrencia->id,
            'matricula_id' => $this->ocorrencia->matricula_id,
            'tipo_ocorrencia' => $this->ocorrencia->tipoOcorrencia?->nome,
        ];
    }
}
