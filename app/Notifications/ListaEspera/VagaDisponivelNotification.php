<?php

namespace App\Notifications\ListaEspera;

use App\Models\ListaEsperaMatricula;
use App\Notifications\Channels\FcmChannel;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VagaDisponivelNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(protected ListaEsperaMatricula $listaEspera) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $aluno = $this->listaEspera->pessoa?->nome ?? 'Aluno';
        $turma = $this->listaEspera->turma?->nome ?? 'Turma';

        return (new MailMessage)
            ->subject("Vaga disponível na turma {$turma}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Uma vaga foi aberta na turma **{$turma}**, para a qual **{$aluno}** está na lista de espera.")
            ->line('Entre em contato com a secretaria o quanto antes para confirmar a matrícula, pois a vaga será oferecida ao próximo da fila caso não haja retorno.')
            ->salutation('Atenciosamente, Equipe Torre 360');
    }

    public function toDatabase(object $notifiable): array
    {
        $aluno = $this->listaEspera->pessoa?->nome ?? 'Aluno';
        $turma = $this->listaEspera->turma?->nome ?? 'Turma';

        $notification = FilamentNotification::make()
            ->title('Vaga disponível!')
            ->body("{$aluno} está na lista de espera da turma {$turma}, que agora tem vaga.")
            ->success();

        $data = $notification->getDatabaseMessage();
        $data['lista_espera_id'] = $this->listaEspera->id;

        return $data;
    }

    public function toPush(object $notifiable): array
    {
        $aluno = $this->listaEspera->pessoa?->nome ?? 'Aluno';
        $turma = $this->listaEspera->turma?->nome ?? 'Turma';

        return [
            'title' => 'Vaga disponível!',
            'body' => "{$aluno} está na lista de espera da turma {$turma}, que agora tem vaga.",
            'data' => [
                'type' => 'lista_espera_vaga_disponivel',
                'lista_espera_id' => (string) $this->listaEspera->id,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'lista_espera_id' => $this->listaEspera->id,
            'turma_id' => $this->listaEspera->turma_id,
            'pessoa_id' => $this->listaEspera->pessoa_id,
        ];
    }
}
