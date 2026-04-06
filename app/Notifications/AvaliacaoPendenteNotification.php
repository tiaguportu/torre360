<?php

namespace App\Notifications;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Models\Avaliacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AvaliacaoPendenteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(protected Avaliacao $avaliacao) {}

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
        $professor = $this->avaliacao->professor;
        $turma = $this->avaliacao->turma?->nome ?? 'N/A';
        $disciplina = $this->avaliacao->disciplina?->nome ?? 'N/A';
        $etapa = $this->avaliacao->etapaAvaliativa?->nome ?? 'N/A';
        $categoria = $this->avaliacao->categoria?->nome ?? 'N/A';
        $data = $this->avaliacao->data_prevista?->format('d/m/Y') ?? 'N/A';
        $url = AvaliacaoResource::getUrl('lancar-notas', ['record' => $this->avaliacao]);

        return (new MailMessage)
            ->subject("Pendência de Lançamento de Notas - {$data} - {$turma} - {$disciplina}")
            ->greeting("Olá, {$professor?->nome}!")
            ->line("Identificamos que o lançamento das notas para a avaliação **{$categoria}** ({$etapa}) de **{$disciplina}** na turma **{$turma}**, programada para **{$data}**, ainda não foi realizado.")
            ->line('Essa pendência impossibilita o fechamento do rendimento escolar do aluno.')
            ->action('Lançar Notas Agora', $url)
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
