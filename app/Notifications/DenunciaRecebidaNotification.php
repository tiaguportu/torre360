<?php

namespace App\Notifications;

use App\Filament\Resources\Denuncias\DenunciaResource;
use App\Models\Denuncia;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso ao comitê da ouvidoria. Leva só o protocolo, a categoria e o prazo: o relato nunca vai por e-mail.
 */
class DenunciaRecebidaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(protected int $denunciaId) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $denuncia = $this->denuncia();

        return (new MailMessage)
            ->subject("Nova manifestação na ouvidoria: {$denuncia->protocolo}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line($this->corpo($denuncia))
            ->action('Abrir a ouvidoria', DenunciaResource::getUrl('index', isAbsolute: true, panel: 'admin'))
            ->line('Por segurança, o relato fica no sistema.')
            ->salutation('Atenciosamente, Torre 360');
    }

    public function toDatabase(object $notifiable): array
    {
        $denuncia = $this->denuncia();

        $mensagem = FilamentNotification::make()
            ->title("Nova manifestação na ouvidoria: {$denuncia->protocolo}")
            ->body($this->corpo($denuncia))
            ->warning()
            ->getDatabaseMessage();
        $mensagem['denuncia_id'] = $this->denunciaId;

        return $mensagem;
    }

    private function denuncia(): Denuncia
    {
        return Denuncia::findOrFail($this->denunciaId);
    }

    private function corpo(Denuncia $denuncia): string
    {
        return 'Categoria: '.$denuncia->categoria->getLabel().'. Prazo para a primeira providência: '.($denuncia->prazo_em?->format('d/m/Y') ?? 'sem prazo').'.'
            .($denuncia->suspeita_violencia ? ' Suspeita de violência contra criança ou adolescente: siga o fluxo de comunicação ao Conselho Tutelar.' : '');
    }
}
