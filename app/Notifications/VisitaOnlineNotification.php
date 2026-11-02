<?php

namespace App\Notifications;

use App\Models\VisitaInteressado;
use App\Services\AgendaVisitaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail à família sobre a visita marcada pela página pública (Onda 86): confirmação, lembrete, troca e cancelamento. Vai por
 * e-mail avulso (a família ainda é lead, sem conta). É mensagem transacional: a pessoa acabou de pedir a visita.
 */
class VisitaOnlineNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const CONFIRMADA = 'confirmada';

    public const LEMBRETE = 'lembrete';

    public const REAGENDADA = 'reagendada';

    public const CANCELADA = 'cancelada';

    public int $tries = 3;

    public function __construct(protected int $visitaId, protected string $evento) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visita = VisitaInteressado::with(['interessado.pessoa', 'unidade'])->findOrFail($this->visitaId);
        $escola = $visita->unidade?->nome ?? config('app.name');
        $quando = $visita->data_hora->copy()->locale('pt_BR')->translatedFormat('l, d/m/Y \à\s H:i');

        $mensagem = (new MailMessage)
            ->subject(match ($this->evento) {
                self::LEMBRETE => "Lembrete da sua visita — {$escola}",
                self::REAGENDADA => "Visita reagendada — {$escola}",
                self::CANCELADA => "Visita cancelada — {$escola}",
                default => "Visita confirmada — {$escola}",
            })
            ->greeting('Olá, '.$visita->interessado->pessoa->nome.'!');

        if ($this->evento === self::CANCELADA) {
            return $mensagem
                ->line("Sua visita à {$escola} marcada para {$quando} foi cancelada.")
                ->line('Quando quiser, é só marcar outro horário.')
                ->action('Marcar nova visita', route('visita.publica.agendar'));
        }

        $mensagem
            ->line(match ($this->evento) {
                self::LEMBRETE => "Sua visita à {$escola} é nas próximas 24 horas. Esperamos você!",
                self::REAGENDADA => "Sua visita à {$escola} mudou de horário.",
                default => "Sua visita à {$escola} está confirmada.",
            })
            ->line("**Quando:** {$quando}")
            ->line((string) config('visitas.o_que_levar'));

        if ($visita->unidade?->telefone) {
            $mensagem->line('**Contato da escola:** '.$visita->unidade->telefone);
        }

        return $mensagem
            ->action('Ver, reagendar ou cancelar', app(AgendaVisitaService::class)->urlDaVisita($visita))
            ->line('Se não puder comparecer, cancele ou troque o horário pelo link acima: assim outra família aproveita a vaga na agenda.');
    }
}
