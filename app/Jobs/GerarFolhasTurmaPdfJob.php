<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Avaliacao;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\Omr\FolhaGabaritoPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GerarFolhasTurmaPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $avaliacaoId,
        public int $userId,
    ) {}

    public function handle(FolhaGabaritoPdfService $pdfService): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $avaliacao = Avaliacao::find($this->avaliacaoId);
        if (! $avaliacao) {
            $user->notify(new SystemNotification(
                title: 'Avaliação não encontrada',
                body: 'A avaliação solicitada para geração de folhas não existe mais.',
                type: 'danger',
            ));

            return;
        }

        try {
            $pdfContent = $pdfService->gerarPdfTurma($avaliacao);

            $fileName = 'gabaritos/folhas_turma_avaliacao_'.$avaliacao->id.'_'.Str::random(8).'.pdf';
            Storage::disk('local')->put($fileName, $pdfContent);

            $url = route('omr.folhas.download', ['path' => base64_encode($fileName)]);

            $user->notify(new SystemNotification(
                title: 'Folhas de Gabarito Geradas',
                body: 'O PDF das folhas de respostas da turma está pronto para impressão.',
                type: 'success',
                actionUrl: $url,
                actionLabel: 'Baixar PDF',
            ));
        } catch (Throwable $e) {
            report($e);
            $user->notify(new SystemNotification(
                title: 'Falha ao gerar folhas de gabarito',
                body: $e->getMessage(),
                type: 'danger',
            ));
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Falha crítica no job GerarFolhasTurmaPdfJob', [
            'avaliacao_id' => $this->avaliacaoId,
            'user_id' => $this->userId,
            'erro' => $exception->getMessage(),
        ]);

        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new SystemNotification(
                title: 'Erro na geração de folhas',
                body: 'Ocorreu um erro inesperado ao gerar as folhas da turma. Tente novamente.',
                type: 'danger',
            ));
        }
    }
}
