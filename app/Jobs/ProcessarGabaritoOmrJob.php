<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Avaliacao;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\Omr\OmrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessarGabaritoOmrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public int $avaliacaoId,
        public string $caminhoArquivo,
        public ?int $userId = null,
        public ?int $matriculaIdManual = null,
    ) {
        $this->onQueue((string) config('omr.fila', 'default'));
    }

    public function handle(OmrService $omrService): void
    {
        $avaliacao = Avaliacao::find($this->avaliacaoId);
        if (! $avaliacao) {
            return;
        }

        try {
            $correcao = $omrService->processarImagem(
                avaliacao: $avaliacao,
                caminhoArquivoOriginal: $this->caminhoArquivo,
                userId: $this->userId,
                matriculaIdManual: $this->matriculaIdManual,
            );

            $user = $this->userId ? User::find($this->userId) : null;
            if ($user && $correcao->estaConfirmada() === false) {
                $user->notify(new SystemNotification(
                    title: 'Gabarito Processado',
                    body: "Folha da avaliação #{$avaliacao->id} processada. Nota prevista: {$correcao->nota_calculada}.",
                    type: 'info',
                ));
            }
        } catch (Throwable $e) {
            report($e);
            Log::error('Erro ao processar imagem de gabarito OMR em background', [
                'avaliacao_id' => $this->avaliacaoId,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Falha crítica no job ProcessarGabaritoOmrJob', [
            'avaliacao_id' => $this->avaliacaoId,
            'arquivo' => $this->caminhoArquivo,
            'erro' => $exception->getMessage(),
        ]);
    }
}
