<?php

namespace App\Notifications;

use App\Models\CronogramaAula;
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
        return ['mail'];
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
        $url = config('app.url')."/admin/cronograma-aulas/{$this->cronogramaAula->id}/frequencia";

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
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
