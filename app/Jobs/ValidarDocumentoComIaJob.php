<?php

namespace App\Jobs;

use App\Models\DocumentoInserido;
use App\Services\DocumentoIaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ValidarDocumentoComIaJob implements ShouldQueue
{
    use Queueable;

    /** Número de tentativas em caso de oscilação momentânea de rede/API */
    public int $tries = 3;

    /** Intervalo de espera em segundos entre tentativas */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $documentoId
    ) {}

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
        } catch (\Throwable $e) {
            Log::warning('Falha ao processar job de validação de documento por IA: '.$e->getMessage(), [
                'documento_id' => $this->documentoId,
                'tentativa' => $this->attempts(),
            ]);

            throw $e;
        }
    }
}
