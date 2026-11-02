<?php

namespace App\Notifications;

use App\Filament\Resources\PropostaComercials\PropostaComercialResource;
use App\Models\PropostaComercial;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso ao consultor sobre a proposta que ele montou (Onda 87): a família aceitou, está para vencer ou expirou.
 */
class PropostaComercialNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const ACEITA = 'aceita';

    public const VENCENDO = 'vencendo';

    public const EXPIRADA = 'expirada';

    public int $tries = 3;

    public function __construct(protected int $propostaId, protected string $evento) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $proposta = $this->proposta();

        return (new MailMessage)
            ->subject($this->titulo($proposta))
            ->greeting("Olá, {$notifiable->name}!")
            ->line($this->corpo($proposta))
            ->action('Abrir as propostas', PropostaComercialResource::getUrl('index', isAbsolute: true, panel: 'admin'))
            ->salutation('Atenciosamente, Torre 360');
    }

    public function toDatabase(object $notifiable): array
    {
        $proposta = $this->proposta();

        $mensagem = FilamentNotification::make()
            ->title($this->titulo($proposta))
            ->body($this->corpo($proposta))
            ->{$this->evento === self::ACEITA ? 'success' : 'warning'}()
            ->getDatabaseMessage();
        $mensagem['proposta_id'] = $this->propostaId;

        return $mensagem;
    }

    private function proposta(): PropostaComercial
    {
        return PropostaComercial::findOrFail($this->propostaId);
    }

    private function titulo(PropostaComercial $proposta): string
    {
        return match ($this->evento) {
            self::ACEITA => "A família aceitou a proposta {$proposta->codigo}",
            self::VENCENDO => "Proposta {$proposta->codigo} perto de vencer",
            default => "Proposta {$proposta->codigo} expirou",
        };
    }

    private function corpo(PropostaComercial $proposta): string
    {
        $familia = $proposta->responsavel_nome;

        return match ($this->evento) {
            self::ACEITA => "{$familia} aceitou em ".$proposta->aceita_em?->format('d/m/Y H:i').'. Combine a matrícula: a proposta aceita é intenção, o contrato é que vale.',
            self::VENCENDO => "A proposta de {$familia} vence em ".$proposta->validade?->format('d/m/Y').'. Fale com a família antes.',
            default => "A proposta de {$familia} passou da validade em ".$proposta->validade?->format('d/m/Y').'. Dá para renovar sem nova aprovação se a tabela não mudou.',
        };
    }
}
