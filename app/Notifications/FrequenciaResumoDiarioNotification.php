<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Carbon\Carbon;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Resumo diário das faltas lançadas para os alunos de um responsável: um aviso só, no lugar de um por aula.
 *
 * Recebe dados simples (montados por `ResumoFaltasDiarioService` na hora da reserva) e não models, assim o job enfileirado
 * não quebra se uma falta for corrigida ou apagada antes de o worker rodar.
 */
class FrequenciaResumoDiarioNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    /**
     * @param  array{dia: string, alunos: list<array{nome: string, faltas: list<array<string, mixed>>}>}  $resumo
     */
    public function __construct(protected array $resumo) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alunos = $this->resumo['alunos'];

        $mensagem = (new MailMessage)
            ->subject('Faltas registradas - '.Carbon::parse($this->resumo['dia'])->format('d/m/Y'))
            ->greeting("Olá, {$notifiable->name}!")
            ->line(count($alunos) === 1
                ? 'Foram registradas faltas para o aluno abaixo.'
                : 'Foram registradas faltas para os alunos abaixo.');

        // Uma linha por falta: `MailMessage::line()` troca as quebras de linha por espaço, então a lista não pode ir numa string só.
        // O Markdown do template junta os itens "- " seguidos em uma lista.
        foreach ($alunos as $aluno) {
            $mensagem->line("**{$aluno['nome']}** ({$this->quantidade(count($aluno['faltas']))})");

            foreach ($aluno['faltas'] as $falta) {
                $mensagem->line('- '.$this->descricao($falta));
            }
        }

        return $mensagem
            ->line('Caso alguma ausência seja um engano, procure a secretaria da escola.')
            ->salutation('Atenciosamente, Equipe Pedagógica / Torre 360');
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->titulo())
            ->body($this->resumoCurto())
            ->warning();

        $mensagem = $notification->getDatabaseMessage();
        $mensagem['frequencia_ids'] = $this->frequenciaIds();

        return $mensagem;
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => $this->titulo(),
            'body' => $this->resumoCurto(),
            'data' => [
                'type' => 'frequencia_resumo_diario',
                'frequencia_ids' => implode(',', $this->frequenciaIds()),
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return ['frequencia_ids' => $this->frequenciaIds()];
    }

    private function titulo(): string
    {
        $alunos = $this->resumo['alunos'];

        return count($alunos) === 1
            ? "Faltas registradas: {$alunos[0]['nome']}"
            : 'Faltas registradas: '.count($alunos).' alunos';
    }

    private function resumoCurto(): string
    {
        $partes = array_map(
            fn (array $aluno) => "{$aluno['nome']}: {$this->quantidade(count($aluno['faltas']))}",
            $this->resumo['alunos'],
        );

        return implode('; ', $partes).'.';
    }

    /**
     * "29/10/2026 · 08:00 às 08:50 · Matemática (Prof. Ana)".
     *
     * @param  array<string, mixed>  $falta
     */
    private function descricao(array $falta): string
    {
        $partes = [];

        if ($falta['data']) {
            $partes[] = Carbon::parse($falta['data'])->format('d/m/Y');
        }

        if ($falta['inicio']) {
            $partes[] = $falta['fim'] ? "{$falta['inicio']} às {$falta['fim']}" : $falta['inicio'];
        }

        $partes[] = $falta['professor'] ? "{$falta['disciplina']} (Prof. {$falta['professor']})" : $falta['disciplina'];

        return implode(' · ', $partes);
    }

    private function quantidade(int $faltas): string
    {
        return $faltas === 1 ? '1 falta' : "{$faltas} faltas";
    }

    /**
     * @return list<int>
     */
    private function frequenciaIds(): array
    {
        $ids = [];

        foreach ($this->resumo['alunos'] as $aluno) {
            foreach ($aluno['faltas'] as $falta) {
                $ids[] = $falta['frequencia_id'];
            }
        }

        return $ids;
    }
}
