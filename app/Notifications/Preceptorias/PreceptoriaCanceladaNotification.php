<?php

namespace App\Notifications\Preceptorias;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa que uma preceptoria já combinada foi excluída (cancelada).
 * Tudo vai pronto como texto: o aviso é montado numa fila, depois de a preceptoria sair das listas, e
 * não pode depender de reencontrá-la.
 */
class PreceptoriaCanceladaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    /**
     * @param  string  $data  "24/08/2026"
     * @param  string  $horario  "07:15" ou "07:15 às 07:45"
     * @param  string|null  $canceladoPor  nome de quem excluiu
     */
    public function __construct(
        public string $data,
        public string $horario,
        public ?string $professor,
        public ?string $aluno,
        public ?string $canceladoPor = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        // Sininho (database) e Push (FCM) apenas para usuários
        if ($notifiable instanceof User) {
            $channels[] = 'database';
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nome = $notifiable instanceof User ? $notifiable->name : $notifiable->nome;

        $mensagem = (new MailMessage)
            ->subject('Preceptoria Cancelada')
            ->greeting("Olá, {$nome}!")
            ->line('Uma preceptoria que estava agendada foi cancelada.')
            ->line('Aluno: '.($this->aluno ?? 'N/D'))
            ->line('Professor: '.($this->professor ?? 'N/D'))
            ->line("Data: {$this->data}")
            ->line("Horário: {$this->horario}");

        if ($this->canceladoPor) {
            $mensagem->line("Cancelada por: {$this->canceladoPor}");
        }

        return $mensagem->action('Ver Horários', PreceptoriaResource::getUrl('index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Preceptoria Cancelada')
            ->body($this->resumo())
            ->actions([
                Action::make('view')
                    ->label('Ver Horários')
                    ->url(PreceptoriaResource::getUrl('index'))
                    ->button(),
            ])
            ->getDatabaseMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'Preceptoria Cancelada',
            'body' => $this->resumo(),
            'data' => [
                'url' => PreceptoriaResource::getUrl('index', isAbsolute: false),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'cancelamento',
            'data' => $this->data,
            'horario' => $this->horario,
        ];
    }

    private function resumo(): string
    {
        return sprintf(
            'A preceptoria de %s com %s, em %s às %s, foi cancelada.',
            $this->aluno ?? 'sem aluno',
            $this->professor ?? 'sem professor',
            $this->data,
            $this->horario,
        );
    }
}
