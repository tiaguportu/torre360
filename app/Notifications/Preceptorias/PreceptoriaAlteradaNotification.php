<?php

namespace App\Notifications\Preceptorias;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\Preceptoria;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa que uma preceptoria já combinada mudou de horário ou de professor.
 * O que era antes vai pronto como texto, porque o registro já foi alterado quando o aviso é montado.
 */
class PreceptoriaAlteradaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const HORARIO = 'horario';

    public const PROFESSOR = 'professor';

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    /**
     * @param  string  $mudanca  HORARIO ou PROFESSOR
     * @param  string  $antes  o valor anterior, pronto para exibir ("24/08/2026 às 07:15" ou o nome do professor)
     * @param  string|null  $alteradoPor  nome de quem fez a mudança
     */
    public function __construct(
        public Preceptoria $preceptoria,
        public string $mudanca,
        public string $antes,
        public ?string $alteradoPor = null,
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
            ->subject($this->titulo())
            ->greeting("Olá, {$nome}!")
            ->line($this->mudanca === self::PROFESSOR
                ? 'O professor de uma preceptoria foi alterado.'
                : 'Uma preceptoria foi reagendada.')
            ->line($this->mudanca === self::PROFESSOR
                ? "Antes: {$this->antes}"
                : "Horário anterior: {$this->antes}")
            ->line("Professor: {$this->professor()}")
            ->line("Aluno: {$this->aluno('N/D')}")
            ->line("Data: {$this->data()}")
            ->line("Horário: {$this->hora()}");

        if ($this->alteradoPor) {
            $mensagem->line("Alterado por: {$this->alteradoPor}");
        }

        return $mensagem->action('Ver Preceptoria', PreceptoriaResource::getUrl('view', ['record' => $this->preceptoria]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->titulo())
            ->body($this->resumo())
            ->actions([
                Action::make('view')
                    ->label('Ver Preceptoria')
                    ->url(PreceptoriaResource::getUrl('view', ['record' => $this->preceptoria]))
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
            'title' => $this->titulo(),
            'body' => $this->resumo(),
            'data' => [
                'url' => PreceptoriaResource::getUrl('view', ['record' => $this->preceptoria], absolute: false),
                'preceptoria_id' => $this->preceptoria->id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'preceptoria_id' => $this->preceptoria->id,
            'mudanca' => $this->mudanca,
        ];
    }

    private function titulo(): string
    {
        return $this->mudanca === self::PROFESSOR ? 'Professor da Preceptoria Alterado' : 'Preceptoria Reagendada';
    }

    private function resumo(): string
    {
        $alunoEProfessor = "{$this->aluno('sem aluno')} e {$this->professor()}";

        return $this->mudanca === self::PROFESSOR
            ? "Preceptoria de {$this->data()} às {$this->hora()} ({$alunoEProfessor}): o professor era {$this->antes}."
            : "Preceptoria entre {$alunoEProfessor} reagendada para {$this->data()} às {$this->hora()} (antes: {$this->antes}).";
    }

    private function data(): string
    {
        return $this->preceptoria->data?->format('d/m/Y') ?? 'N/D';
    }

    private function hora(): string
    {
        return $this->preceptoria->hora_inicio?->format('H:i') ?? 'N/D';
    }

    private function professor(): string
    {
        return $this->preceptoria->professor?->nome ?? 'N/D';
    }

    private function aluno(string $padrao): string
    {
        return $this->preceptoria->matricula?->pessoa?->nome ?? $padrao;
    }
}
