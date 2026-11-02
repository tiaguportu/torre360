<?php

namespace App\Notifications;

use App\Filament\Portal\Pages\CentralAtendimento;
use App\Models\AtendimentoChamado;
use App\Notifications\Channels\FcmChannel;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso à família de que o chamado teve uma resposta ou foi encerrado (Onda 83). O conteúdo é o mínimo: o protocolo e um
 * caminho para o Portal autenticado. Nunca leva o texto da resposta, o motivo de um indeferimento nem o assunto, que podem ser
 * dado de criança, de saúde ou o pedido sigiloso de um titular da LGPD.
 */
class ChamadoRespondidoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const RESPOSTA = 'resposta';

    public const ENCERRADO = 'encerrado';

    public const FECHADO_POR_INATIVIDADE = 'inatividade';

    public int $tries = 3;

    public function __construct(protected int $chamadoId, protected string $evento = self::RESPOSTA) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $chamado = $this->chamado();

        return (new MailMessage)
            ->subject($this->titulo($chamado))
            ->greeting("Olá, {$notifiable->name}!")
            ->line($this->corpo())
            ->action('Abrir a Central de Atendimento', CentralAtendimento::getUrl(isAbsolute: true, panel: 'portal'))
            ->line('Por segurança, o conteúdo da resposta fica no Portal.')
            ->salutation('Atenciosamente, Torre 360');
    }

    public function toDatabase(object $notifiable): array
    {
        $chamado = $this->chamado();

        $mensagem = FilamentNotification::make()
            ->title($this->titulo($chamado))
            ->body($this->corpo())
            ->info()
            ->getDatabaseMessage();
        $mensagem['chamado_id'] = $this->chamadoId;

        return $mensagem;
    }

    public function toPush(object $notifiable): array
    {
        $chamado = $this->chamado();

        return [
            'title' => $this->titulo($chamado),
            'body' => $this->corpo(),
            'data' => ['type' => 'chamado', 'chamado_id' => (string) $this->chamadoId],
        ];
    }

    private function chamado(): AtendimentoChamado
    {
        return AtendimentoChamado::findOrFail($this->chamadoId);
    }

    private function titulo(AtendimentoChamado $chamado): string
    {
        return match ($this->evento) {
            self::ENCERRADO => "Seu pedido {$chamado->protocolo} foi concluído",
            self::FECHADO_POR_INATIVIDADE => "Chamado {$chamado->protocolo} encerrado",
            default => "Resposta ao chamado {$chamado->protocolo}",
        };
    }

    private function corpo(): string
    {
        return match ($this->evento) {
            self::ENCERRADO => 'A escola concluiu o seu pedido. Veja o resultado na Central de Atendimento do Portal.',
            self::FECHADO_POR_INATIVIDADE => 'O chamado foi encerrado por falta de resposta. Se ainda precisar, responda no Portal e ele será reaberto.',
            default => 'A escola respondeu ao seu chamado. Leia a resposta na Central de Atendimento do Portal.',
        };
    }
}
