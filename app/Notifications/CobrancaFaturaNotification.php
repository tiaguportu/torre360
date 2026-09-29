<?php

namespace App\Notifications;

use App\Models\Fatura;
use App\Models\Pessoa;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CobrancaFaturaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public Fatura $fatura,
        public Pessoa $responsavel,
        public string $assunto,
        public string $mensagem,
        public array $canais = ['mail', 'database'],
        public string $tipo = 'warning'
    ) {}

    public function via(object $notifiable): array
    {
        $via = [];

        if (in_array('email', $this->canais) || in_array('mail', $this->canais) || in_array('todos', $this->canais)) {
            $via[] = 'mail';
        }

        // Database e FCM necessitam de entidade User persistida
        if ($notifiable instanceof User) {
            if (in_array('portal', $this->canais) || in_array('todos', $this->canais)) {
                $via[] = 'database';
            }

            if (in_array('push', $this->canais) || in_array('todos', $this->canais)) {
                $via[] = FcmChannel::class;
            }
        }

        return ! empty($via) ? $via : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nome = $this->responsavel->nome ?? ($notifiable->name ?? 'Responsável');

        $mail = (new MailMessage)
            ->subject($this->assunto)
            ->greeting("Olá, {$nome}")
            ->line($this->mensagem)
            ->line('Detalhes da Fatura:')
            ->line("• Fatura: #{$this->fatura->id}")
            ->line("• Vencimento: {$this->fatura->vencimento?->format('d/m/Y')}")
            ->line('• Saldo a Pagar: R$ '.number_format($this->fatura->valor_restante, 2, ',', '.'));

        $url = url("/admin/faturas/{$this->fatura->id}");
        $mail->action('Acessar Fatura no Portal', $url);

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        $notif = FilamentNotification::make()
            ->title($this->assunto)
            ->body($this->mensagem);

        if ($this->tipo === 'danger') {
            $notif->danger();
        } elseif ($this->tipo === 'warning') {
            $notif->warning();
        } else {
            $notif->info();
        }

        return $notif->toArray();
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'token' => $notifiable->fcm_token ?? null,
            'title' => $this->assunto,
            'body' => mb_strimwidth($this->mensagem, 0, 150, '...'),
            'data' => [
                'type' => 'fatura_cobranca',
                'fatura_id' => (string) $this->fatura->id,
            ],
        ];
    }
}
