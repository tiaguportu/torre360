<?php

namespace App\Notifications;

use App\Filament\Resources\AtendimentoChamados\AtendimentoChamadoResource;
use App\Models\AtendimentoChamado;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso à equipe da Central de Atendimento (Onda 83): chamado novo, resposta da família, chamado atribuído.
 *
 * Vai também ao e-mail do setor (`AtendimentoSetor.email_notificacao`, que até aqui era cadastrado e nunca lido). Chamado
 * restrito (pedido do titular da LGPD) não revela o assunto nem a mensagem: só o protocolo.
 */
class ChamadoParaEquipeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const NOVO = 'novo';

    public const RESPOSTA_FAMILIA = 'resposta_familia';

    public const ATRIBUIDO = 'atribuido';

    public int $tries = 3;

    public function __construct(protected int $chamadoId, protected string $evento = self::NOVO) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $chamado = $this->chamado();

        return (new MailMessage)
            ->subject($this->titulo($chamado))
            ->greeting($notifiable instanceof User ? "Olá, {$notifiable->name}!" : 'Olá!')
            ->line($this->corpo($chamado))
            ->action('Abrir a Central de Atendimento', $this->url())
            ->salutation('Atenciosamente, Torre 360');
    }

    public function toDatabase(object $notifiable): array
    {
        $chamado = $this->chamado();

        $mensagem = FilamentNotification::make()
            ->title($this->titulo($chamado))
            ->body($this->corpo($chamado))
            ->warning()
            ->getDatabaseMessage();
        $mensagem['chamado_id'] = $this->chamadoId;

        return $mensagem;
    }

    private function chamado(): AtendimentoChamado
    {
        return AtendimentoChamado::with(['setor', 'solicitante'])->findOrFail($this->chamadoId);
    }

    private function titulo(AtendimentoChamado $chamado): string
    {
        $prefixo = match ($this->evento) {
            self::RESPOSTA_FAMILIA => 'A família respondeu',
            self::ATRIBUIDO => 'Chamado atribuído a você',
            default => $chamado->ehRequerimento() ? 'Novo requerimento' : 'Novo chamado',
        };

        return "{$prefixo}: {$chamado->protocolo}";
    }

    private function corpo(AtendimentoChamado $chamado): string
    {
        if ($chamado->restrito) {
            return 'Há um pedido de acesso restrito esperando atendimento. Abra a Central de Atendimento para ler.';
        }

        $partes = [$chamado->setor?->nome, $chamado->assunto, $chamado->solicitante?->nome];
        $prazo = $chamado->prazo_primeira_resposta_em && $chamado->primeira_resposta_em === null
            ? ' Responder até '.$chamado->prazo_primeira_resposta_em->format('d/m/Y H:i').'.'
            : '';

        return implode(' · ', array_filter($partes)).'.'.$prazo;
    }

    private function url(): string
    {
        return AtendimentoChamadoResource::getUrl('index', isAbsolute: true, panel: 'admin');
    }
}
