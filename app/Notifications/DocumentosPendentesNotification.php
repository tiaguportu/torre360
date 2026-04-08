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
        $faltantes = $this->matricula->getMissingMandatoryDocuments();
        $rejeitados = $this->matricula->getRejectedDocuments();

        $mensagem = (new MailMessage)
            ->subject("Aviso de Documentos Pendentes - {$codigo}")
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line("Constatamos que existem documentos obrigatórios que precisam ser regularizados para a matrícula do(a) aluno(a) **{$alunoNome}**.");

        if ($faltantes->isNotEmpty()) {
            $mensagem->line('**Documentos não enviados:**');
            foreach ($faltantes as $doc) {
                $mensagem->line("- {$doc->nome}");
            }
        }

        if ($rejeitados->isNotEmpty()) {
            if ($faltantes->isNotEmpty()) {
                $mensagem->line(' ');
            }
            $mensagem->line('**Documentos que precisam ser reenviados (Rejeitados):**');
            foreach ($rejeitados as $docInserido) {
                $mensagem->line("- **{$docInserido->tipoDocumento->nome}**");
                if ($docInserido->observacoes) {
                    $mensagem->line("  - Motivo/Observação: *{$docInserido->observacoes}*");
                }
            }
        }

        $url = route('filament.admin.resources.matriculas.documentos', ['record' => $this->matricula->id]);

        return $mensagem
            ->line(' ')
            ->line('A regularização desses documentos é essencial para a manutenção da matrícula.')
            ->action('Gerenciar Documentos', $url)
            ->line('Se você já enviou os documentos recentes para análise, por favor, ignore este aviso.')
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
