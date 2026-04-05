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
        $contrato = $this->matricula->contrato;
        $mensagem = (new MailMessage)
            ->subject('Aviso de Documentos Pendentes')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Constatamos que existem documentos obrigatórios e ativos pendentes de envio.');

        if ($contrato) {
            $matriculasComPendencia = $contrato->matriculas()
                ->get()
                ->filter(fn ($m) => $m->hasMissingMandatoryDocuments());

            foreach ($matriculasComPendencia as $m) {
                $alunoNome = $m->pessoa?->nome ?? 'Aluno(a)';
                $codigo = $m->codigo;
                $docs = $m->getMissingMandatoryDocuments()->pluck('nome')->toArray();

                $mensagem->line("**Matrícula: {$codigo} - {$alunoNome}**");
                foreach ($docs as $doc) {
                    $mensagem->line("- {$doc}");
                }
            }
        } else {
            // Fallback para apenas a matrícula atual se não houver contrato (não deve acontecer conforme estrutura)
            $alunoNome = $this->matricula->pessoa?->nome ?? 'Aluno(a)';
            $docs = $this->matricula->getMissingMandatoryDocuments()->pluck('nome')->toArray();

            $mensagem->line("**Aluno(a): {$alunoNome}**");
            foreach ($docs as $doc) {
                $mensagem->line("- {$doc}");
            }
        }

        $url = route('filament.admin.resources.matriculas.documentos', ['record' => $this->matricula->id]);

        return $mensagem
            ->line('A regularização desses documentos é essencial para a manutenção da(s) matrícula(s).')
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
