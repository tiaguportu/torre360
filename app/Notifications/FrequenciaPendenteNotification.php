<?php

namespace App\Notifications;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use App\Models\CronogramaAula;
use App\Notifications\Channels\FcmChannel;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FrequenciaPendenteNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(protected CronogramaAula $cronogramaAula) {}

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
        $professor = $this->cronogramaAula->professor;
        $turma = $this->cronogramaAula->turma?->nome ?? 'N/A';
        $disciplina = $this->cronogramaAula->disciplina?->nome ?? 'N/A';
        $data = optional($this->cronogramaAula->data)->format('d/m/Y') ?? 'N/A';
        $url = CronogramaAulaResource::getUrl('lancar-frequencia', ['record' => $this->cronogramaAula]);

        return (new MailMessage)
            ->subject("Pendência de Lançamento de Frequência - {$data} - {$turma} - {$disciplina}")
            ->greeting("Olá, {$professor?->nome}!")
            ->line("Identificamos que o lançamento da frequência para a aula de **{$disciplina}** na turma **{$turma}**, realizada em **{$data}**, ainda não foi concluído.")
            ->line('Essa pendência pode afetar o acompanhamento pedagógico dos alunos.')
            ->action('Lançar Frequência Agora', $url)
            ->line('Por favor, acesse o sistema para regularizar o lançamento.')
            ->line('Obrigado pela sua colaboração!')
            ->salutation('Atenciosamente, Equipe Torre 360');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $turma = $this->cronogramaAula->turma?->nome ?? 'N/A';
        $disciplina = $this->cronogramaAula->disciplina?->nome ?? 'N/A';
        $data = optional($this->cronogramaAula->data)->format('d/m/Y') ?? 'N/A';
        $url = CronogramaAulaResource::getUrl('lancar-frequencia', ['record' => $this->cronogramaAula]);

        return FilamentNotification::make()
            ->title('Pendência de Frequência - '.$turma)
            ->body("Lançamento pendente para {$disciplina} em {$data}.")
            ->warning()
            ->actions([
                FilamentAction::make('view')
                    ->label('Lançar Agora')
                    ->url($url)
                    ->button(),
            ])
            ->getDatabaseMessage();
    }

    /**
     * Get the push representation of the notification.
     */
    public function toPush(object $notifiable): array
    {
        $turma = $this->cronogramaAula->turma?->nome ?? 'N/A';
        $disciplina = $this->cronogramaAula->disciplina?->nome ?? 'N/A';
        $data = optional($this->cronogramaAula->data)->format('d/m/Y') ?? 'N/A';
        $url = CronogramaAulaResource::getUrl('lancar-frequencia', ['record' => $this->cronogramaAula]);

        return [
            'title' => 'Pendência de Frequência - Torre360',
            'body' => "Lançamento pendente: {$disciplina} ({$turma}) em {$data}.",
            'data' => [
                'type' => 'frequencia_pendente',
                'url' => $url,
                'cronograma_aula_id' => (string) $this->cronogramaAula->id,
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'cronograma_aula_id' => $this->cronogramaAula->id,
            'turma' => $this->cronogramaAula->turma?->nome,
            'disciplina' => $this->cronogramaAula->disciplina?->nome,
        ];
    }
}
