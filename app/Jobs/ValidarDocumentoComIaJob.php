<?php

namespace App\Jobs;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\DocumentoInserido;
use App\Services\DocumentoIaService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Análise de documento do candidato por IA (Gemini). Roda na fila própria `crm.fila_ia`: uma chamada pode
 * levar minutos e, na fila padrão, atrasava e-mails e notificações.
 */
class ValidarDocumentoComIaJob implements ShouldQueue
{
    use Queueable;

    /** Número de tentativas em caso de oscilação momentânea de rede/API */
    public int $tries = 3;

    /** Intervalo de espera em segundos entre tentativas */
    public int $backoff = 10;

    /**
     * Tempo máximo de cada tentativa. Fica abaixo do `retry_after` da fila (90 s): se passasse, a fila
     * devolveria o job a outro worker enquanto este ainda trabalha, e o documento seria analisado em dobro.
     * Acima do orçamento de tempo da chamada ao Gemini (`services.gemini.orcamento_documento_segundos`).
     */
    public int $timeout = 85;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $documentoId
    ) {
        $this->onQueue((string) config('crm.fila_ia', 'ia'));
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentoIaService $service): void
    {
        $documento = DocumentoInserido::find($this->documentoId);

        if (! $documento || empty($documento->arquivo_path)) {
            return;
        }

        try {
            $service->analisarDocumento($documento);
        } catch (Throwable $e) {
            Log::warning('Falha ao processar job de validação de documento por IA: '.$e->getMessage(), [
                'documento_id' => $this->documentoId,
                'tentativa' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Esgotadas as tentativas: avisa o consultor do lead para conferir o documento manualmente. Sem isso a
     * falha ficava só no log e o documento aparecia "Não analisado" sem que ninguém soubesse por quê.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Análise de documento por IA esgotou as tentativas.', [
            'documento_id' => $this->documentoId,
            'erro' => $exception?->getMessage(),
        ]);

        try {
            $documento = DocumentoInserido::query()->with(['tipoDocumento', 'interessado.usuario', 'interessado.pessoa'])->find($this->documentoId);
            $consultor = $documento?->interessado?->usuario;

            if (! $documento || ! $consultor) {
                return;
            }

            Notification::make()
                ->title('Análise por IA indisponível')
                ->body('Não foi possível analisar o documento "'.($documento->tipoDocumento?->nome ?? 'enviado').'" de '.($documento->interessado->pessoa?->nome ?? 'um candidato').'. Confira-o manualmente na ficha do lead.')
                ->icon('heroicon-o-exclamation-triangle')
                ->warning()
                ->actions([
                    Action::make('ver')
                        ->label('Ver lead')
                        ->url(InteressadoResource::getUrl('edit', ['record' => $documento->interessado]))
                        ->button(),
                ])
                ->sendToDatabase($consultor);
        } catch (Throwable $e) {
            Log::warning('Não foi possível avisar o consultor sobre a falha da análise de documento: '.$e->getMessage());
        }
    }
}
