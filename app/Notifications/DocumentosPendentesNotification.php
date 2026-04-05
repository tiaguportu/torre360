<?php

namespace App\Notifications;

use App\Models\Matricula;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentosPendentesNotification extends Notification
{
    use Queueable;

    public function __construct(public Matricula $matricula)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alunoNome = $this->matricula->pessoa?->nome ?? 'Aluno(a)';
        $codigo = $this->matricula->codigo;
        $docs = $this->matricula->getMissingMandatoryDocuments()->pluck('nome')->toArray();

        $mensagem = (new MailMessage)
            ->subject("Aviso de Documentos Pendentes - {$codigo}")
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line("Constatamos que existem documentos obrigatórios e ativos pendentes para a matrícula do(a) aluno(a) **{$alunoNome}**.")
            ->line('Os documentos pendentes são:');

        foreach ($docs as $doc) {
            $mensagem->line("- {$doc}");
        }

        $url = route('filament.admin.resources.matriculas.documentos', ['record' => $this->matricula->id]);

        return $mensagem
            ->line('A regularização desses documentos é essencial para a manutenção da matrícula.')
            ->action('Gerenciar Documentos', $url)
            ->line('Se você já enviou os documentos, por favor, ignore este aviso.')
            ->salutation('Atenciosamente, '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'matricula_id' => $this->matricula->id,
            'aluno_nome' => $this->matricula->pessoa->nome,
        ];
    }
}
