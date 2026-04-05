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
        $aluno = $this->matricula->pessoa;
        $url = route('filament.admin.resources.matriculas.documentos', ['record' => $this->matricula->id]);

        return (new MailMessage)
            ->subject('Aviso de Documentos Pendentes - '.$aluno->nome)
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Constatamos que a matrícula do(a) aluno(a) **'.$aluno->nome.'** possui documentos obrigatórios pendentes de envio.')
            ->line('A regularização desses documentos é essencial para a manutenção da matrícula.')
            ->action('Enviar Documentos', $url)
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
