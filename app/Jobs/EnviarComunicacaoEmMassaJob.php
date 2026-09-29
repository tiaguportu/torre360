<?php

namespace App\Jobs;

use App\Enums\StatusComunicacaoEmMassa;
use App\Models\ComunicacaoEmMassa;
use App\Models\Pessoa;
use App\Services\CanalMensagemManager;
use App\Services\ComunicacaoEmMassaService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarComunicacaoEmMassaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public ComunicacaoEmMassa $comunicacao) {}

    public function handle(ComunicacaoEmMassaService $service): void
    {
        if (! $this->comunicacao->podeSerEnviada()) {
            return;
        }

        $this->comunicacao->update(['status' => StatusComunicacaoEmMassa::Enviando]);

        $canal = CanalMensagemManager::resolver($this->comunicacao->canal);
        $destinatarios = $service->destinatarios($this->comunicacao);

        $enviados = 0;
        $falhas = 0;

        foreach ($destinatarios as $pessoa) {
            $sucesso = $canal->enviar(
                $pessoa,
                $this->personalizar($this->comunicacao->assunto, $pessoa),
                $this->personalizar($this->comunicacao->corpo, $pessoa),
            );

            $sucesso ? $enviados++ : $falhas++;
        }

        $this->comunicacao->update([
            'status' => StatusComunicacaoEmMassa::Concluida,
            'total_destinatarios' => $destinatarios->count(),
            'total_enviados' => $enviados,
            'total_falhas' => $falhas,
            'enviado_em' => now(),
        ]);

        $this->notificarConclusao($enviados, $falhas, $destinatarios->count());
    }

    public function failed(\Throwable $e): void
    {
        $this->comunicacao->update(['status' => StatusComunicacaoEmMassa::Falhou]);
    }

    /**
     * Substitui a variável [Nome] pelo primeiro nome do destinatário, mesmo
     * padrão de variáveis usado nos modelos de WhatsApp do CRM.
     */
    private function personalizar(string $texto, Pessoa $pessoa): string
    {
        return str_replace('[Nome]', explode(' ', trim($pessoa->nome))[0], $texto);
    }

    private function notificarConclusao(int $enviados, int $falhas, int $total): void
    {
        $destinatario = $this->comunicacao->enviadoPor;

        if (! $destinatario) {
            return;
        }

        Notification::make()
            ->title('Comunicação em massa concluída')
            ->body("\"{$this->comunicacao->nome}\": {$enviados} de {$total} enviados".($falhas > 0 ? ", {$falhas} falha(s)." : '.'))
            ->color($falhas > 0 ? 'warning' : 'success')
            ->sendToDatabase($destinatario);
    }
}
