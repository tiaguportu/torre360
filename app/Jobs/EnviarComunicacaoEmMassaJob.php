<?php

namespace App\Jobs;

use App\Enums\StatusComunicacaoEmMassa;
use App\Models\ComunicacaoEmMassa;
use App\Models\Pessoa;
use App\Services\CanalMensagemManager;
use App\Services\ComunicacaoEmMassaService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnviarComunicacaoEmMassaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

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
            try {
                $sucesso = $canal->enviar(
                    $pessoa,
                    $this->personalizar($this->comunicacao->assunto, $pessoa),
                    $this->personalizar($this->comunicacao->corpo, $pessoa),
                );

                $sucesso ? $enviados++ : $falhas++;
            } catch (Throwable $e) {
                $falhas++;
                Log::warning("Falha ao enviar comunicação em massa #{$this->comunicacao->id} para destinatário #{$pessoa->id}: {$e->getMessage()}", [
                    'comunicacao_id' => $this->comunicacao->id,
                    'pessoa_id' => $pessoa->id,
                    'exception' => $e,
                ]);
            }
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

    public function failed(Throwable $e): void
    {
        Log::error("Falha inesperada no processamento da comunicação em massa #{$this->comunicacao->id}: {$e->getMessage()}", [
            'comunicacao_id' => $this->comunicacao->id,
            'exception' => $e,
        ]);

        $this->comunicacao->update(['status' => StatusComunicacaoEmMassa::Falhou]);

        $destinatario = $this->comunicacao->enviadoPor;
        if (! $destinatario) {
            return;
        }

        Notification::make()
            ->title('Falha no envio da comunicação em massa')
            ->body("Ocorreu um erro ao processar a comunicação \"{$this->comunicacao->nome}\".")
            ->color('danger')
            ->sendToDatabase($destinatario);
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
