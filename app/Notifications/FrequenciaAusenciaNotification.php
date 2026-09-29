<?php

namespace App\Notifications;

use App\Models\FrequenciaEscolar;
use App\Notifications\Channels\FcmChannel;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa o responsável quando uma falta é lançada para o aluno.
 */
class FrequenciaAusenciaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(protected FrequenciaEscolar $frequencia) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $aluno = $this->frequencia->matricula?->pessoa?->nome ?? 'Aluno';
        $aula = $this->frequencia->cronogramaAula;
        $disciplina = $aula?->disciplina?->nome ?? 'Aula';
        $data = $aula?->data?->format('d/m/Y') ?? 'N/A';

        return (new MailMessage)
            ->subject("Falta registrada - {$aluno}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Foi registrada uma falta para o aluno **{$aluno}**.")
            ->line("**Disciplina:** {$disciplina}")
            ->line("**Data:** {$data}")
            ->line('Caso a ausência seja um engano, procure a secretaria da escola.')
            ->salutation('Atenciosamente, Equipe Pedagógica / Torre 360');
    }

    public function toDatabase(object $notifiable): array
    {
        $aluno = $this->frequencia->matricula?->pessoa?->nome ?? 'Aluno';
        $aula = $this->frequencia->cronogramaAula;
        $disciplina = $aula?->disciplina?->nome ?? 'Aula';
        $dataAula = $aula?->data?->format('d/m/Y');

        $notification = FilamentNotification::make()
            ->title("Falta registrada: {$aluno}")
            ->body("{$disciplina} em {$dataAula}.")
            ->warning();

        $mensagem = $notification->getDatabaseMessage();
        $mensagem['frequencia_id'] = $this->frequencia->id;

        return $mensagem;
    }

    public function toPush(object $notifiable): array
    {
        $aluno = $this->frequencia->matricula?->pessoa?->nome ?? 'Aluno';
        $disciplina = $this->frequencia->cronogramaAula?->disciplina?->nome ?? 'Aula';

        return [
            'title' => "Falta registrada - {$aluno}",
            'body' => "Falta em {$disciplina}.",
            'data' => [
                'type' => 'frequencia_ausencia',
                'frequencia_id' => (string) $this->frequencia->id,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'frequencia_id' => $this->frequencia->id,
            'matricula_id' => $this->frequencia->matricula_id,
        ];
    }
}
