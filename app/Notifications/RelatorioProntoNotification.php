<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExecucaoRelatorio;
use App\Models\RelatorioSalvo;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de que um relatório agendado ficou pronto (Onda 72). O e-mail leva só o nome do relatório e o link: o arquivo tem dados
 * da escola (às vezes pessoais) e só se baixa dentro do sistema, com login e a permissão da fonte.
 */
class RelatorioProntoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public RelatorioSalvo $relatorio, public ExecucaoRelatorio $execucao) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function url(): string
    {
        return route('relatorios.execucoes.download', $this->execucao);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $aviso = $this->execucao->truncado ? ' O relatório passou do limite de linhas e saiu cortado.' : '';

        return (new MailMessage)
            ->subject("Relatório pronto: {$this->relatorio->nome}")
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line("O relatório agendado \"{$this->relatorio->nome}\" foi gerado com {$this->execucao->linhas} linha(s).{$aviso}")
            ->action('Baixar no sistema', $this->url())
            ->line('Por segurança, o arquivo não vai por e-mail: o link pede o seu login e vale enquanto o arquivo estiver guardado.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Relatório pronto: {$this->relatorio->nome}")
            ->body("{$this->execucao->linhas} linha(s)".($this->execucao->truncado ? ', cortado no limite' : '').'.')
            ->icon('heroicon-o-document-chart-bar')
            ->iconColor('success')
            ->actions([
                FilamentAction::make('baixar')->label('Baixar')->url($this->url())->button(),
            ])
            ->getDatabaseMessage();
    }
}
