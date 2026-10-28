<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\InformeAnualMensalidadesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Job assíncrono para geração de relatório volumoso de Informes Anuais de Mensalidades em Lote.
 *
 * Desacopla o processamento de centenas de páginas em PDF do ciclo de vida da requisição HTTP,
 * gravando o arquivo em cache no disco privado e notificando o usuário com link temporário assinado.
 */
class GerarInformeAnualLotePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $ano,
        public ?int $turmaId,
        public ?string $busca,
        public int $userId,
    ) {}

    public function handle(InformeAnualMensalidadesService $informesService): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->is_active) {
            return;
        }

        // Chave determinística para cache privado inteligente
        $termoBusca = $this->busca ? trim($this->busca) : '';
        $turmaKey = $this->turmaId ? (string) $this->turmaId : 'todas';
        $cacheHash = hash('sha256', "informe_anual_lote:{$this->ano}:{$turmaKey}:{$termoBusca}");
        $token = "informes-{$this->ano}-".substr($cacheHash, 0, 24);
        $caminhoRelativo = "relatorios_gerados/{$token}.pdf";
        $disco = Storage::disk('local');

        $gerouNovo = false;

        // Verifica se já existe um arquivo recente em cache no disco privado (gerado nas últimas 6 horas)
        $existeEmCache = $disco->exists($caminhoRelativo);
        $cacheValido = $existeEmCache && (now()->timestamp - $disco->lastModified($caminhoRelativo)) < 21600;

        if (! $cacheValido) {
            $linhas = $informesService->gerar(
                $this->ano,
                null,
                $this->turmaId,
                $this->busca !== '' ? $this->busca : null
            );

            if ($linhas->isEmpty()) {
                Notification::make()
                    ->title('Informe Anual: Nenhum dado encontrado')
                    ->body("Não foram encontrados pagamentos registrados para os filtros aplicados no ano {$this->ano}.")
                    ->icon('heroicon-o-exclamation-triangle')
                    ->warning()
                    ->sendToDatabase($user);

                $user->notify(new SystemNotification(
                    title: 'Informe Anual: Nenhum dado encontrado',
                    body: "Não foram encontrados registros para os filtros aplicados no ano {$this->ano}.",
                    type: 'warning',
                ));

                return;
            }

            $pdf = Pdf::loadView('informes.mensalidades-anual', [
                'informes' => $linhas,
                'ano' => $this->ano,
                'alunoSemPagamento' => null,
            ])->setPaper('a4');

            $disco->put($caminhoRelativo, $pdf->output());
            $gerouNovo = true;

            activity('informe_anual')
                ->causedBy($user)
                ->withProperties([
                    'ano' => $this->ano,
                    'turma_id' => $this->turmaId,
                    'busca' => $this->busca,
                    'total_alunos' => $linhas->count(),
                    'token' => $token,
                ])
                ->log("Informe anual de mensalidades gerado em lote em segundo plano ({$linhas->count()} alunos).");
        }

        // Gera a URL temporária assinada pelo Laravel, válida por 24 horas
        $downloadUrl = URL::temporarySignedRoute(
            'relatorios.download-temporario',
            now()->addHours(24),
            [
                'token' => $token,
                'filename' => "informes-mensalidades-{$this->ano}.pdf",
            ]
        );

        $mensagemCorpo = $gerouNovo
            ? "O PDF consolidado do ano {$this->ano} foi processado e está pronto para download."
            : 'O relatório solicitado já estava disponível em cache e pronto para download.';

        Notification::make()
            ->title('Informe Anual em Lote Pronto')
            ->body($mensagemCorpo)
            ->icon('heroicon-o-document-chart-bar')
            ->color('success')
            ->actions([
                NotificationAction::make('download')
                    ->label('Baixar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url($downloadUrl)
                    ->openUrlInNewTab(),
            ])
            ->sendToDatabase($user);

        $user->notify(new SystemNotification(
            title: 'Informe Anual em Lote pronto para download',
            body: $mensagemCorpo,
            actionUrl: $downloadUrl,
            actionLabel: 'Baixar PDF',
            type: 'success',
        ));
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Falha ao gerar relatório de informe anual em lote: {$exception->getMessage()}", [
            'ano' => $this->ano,
            'turma_id' => $this->turmaId,
            'busca' => $this->busca,
            'user_id' => $this->userId,
        ]);

        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Falha ao processar Informe Anual em Lote')
            ->body('Ocorreu um erro inesperado ao gerar o relatório consolidado. Por favor, tente novamente.')
            ->icon('heroicon-o-x-circle')
            ->danger()
            ->sendToDatabase($user);

        $user->notify(new SystemNotification(
            title: 'Falha ao processar Informe Anual em Lote',
            body: 'Ocorreu um erro inesperado ao gerar o relatório consolidado. Por favor, tente novamente.',
            type: 'danger',
        ));
    }
}
