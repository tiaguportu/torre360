<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de conteúdo livre (assunto e corpo HTML definidos pelo remetente),
 * usado pelo `EmailCanal` para a comunicação em massa do CRM e pela régua de follow-up.
 *
 * Com `$linkDescadastro` (régua), o e-mail ganha um rodapé com o link para a pessoa parar de receber mensagens
 * comerciais e os cabeçalhos `List-Unsubscribe`, que os provedores mostram como botão "cancelar inscrição".
 */
class MensagemGenericaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $assunto,
        public string $corpoHtml,
        public ?string $linkDescadastro = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->corpoHtml.$this->rodapeDescadastro(),
        );
    }

    public function headers(): Headers
    {
        if (! $this->linkDescadastro) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->linkDescadastro.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    private function rodapeDescadastro(): string
    {
        if (! $this->linkDescadastro) {
            return '';
        }

        $link = e($this->linkDescadastro);

        return '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0 12px">'
            .'<p style="font-size:12px;color:#6b7280;line-height:1.5">Você recebe esta mensagem porque pediu informações à escola. '
            ."Se não quiser mais receber e-mails como este, <a href=\"{$link}\" style=\"color:#6b7280\">clique aqui para cancelar</a>.</p>";
    }
}
