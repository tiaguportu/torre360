<?php

namespace App\Jobs;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use App\Models\TemplateCracha;
use App\Models\User;
use App\Notifications\SystemNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GerarCrachasTurmaPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 120, 300];

    /**
     * @param  array<int>  $turmaIds
     */
    public function __construct(
        public array $turmaIds,
        public int $templateCrachaId,
        public int $userId,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $template = TemplateCracha::find($this->templateCrachaId);
        if (! $template) {
            $user->notify(new SystemNotification(
                title: 'Não foi possível gerar os crachás',
                body: 'O modelo de crachá selecionado não foi encontrado.',
                type: 'danger',
            ));

            return;
        }

        $matriculas = Matricula::query()
            ->whereIn('turma_id', $this->turmaIds)
            ->where('situacao', SituacaoMatricula::ATIVA)
            ->with(['pessoa', 'turma'])
            ->get();

        $pessoasComTurma = $matriculas
            ->filter(fn (Matricula $matricula): bool => $matricula->pessoa !== null)
            ->map(fn (Matricula $matricula): object => (object) [
                'pessoa' => $matricula->pessoa,
                'turma' => $matricula->turma,
            ])
            ->values();

        if ($pessoasComTurma->isEmpty()) {
            $user->notify(new SystemNotification(
                title: 'Não foi possível gerar os crachás',
                body: 'Nenhum aluno ativo foi encontrado nas turmas selecionadas.',
                type: 'warning',
            ));

            return;
        }

        $layout = $template->dados_layout;
        $objects = $layout['objects'] ?? [];
        $backgroundImage = $layout['backgroundImage']['src'] ?? null;

        $crachaLargura = $template->largura * 0.75;
        $crachaAltura = $template->altura * 0.75;

        $pdf = Pdf::loadView('pdf.cracha-lote', [
            'pessoasComTurma' => $pessoasComTurma,
            'objects' => $objects,
            'backgroundImage' => $backgroundImage,
            'crachaLargura' => $crachaLargura,
            'crachaAltura' => $crachaAltura,
        ])->setPaper('a4', 'portrait');

        $path = 'crachas/'.$this->userId.'/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        $user->notify(new SystemNotification(
            title: 'Crachás prontos para download',
            body: 'Os crachás dos alunos foram gerados com sucesso.',
            actionUrl: route('documentos.visualizar', ['path' => $path]),
            actionLabel: 'Baixar PDF',
            type: 'success',
        ));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Falha ao gerar crachás da turma em lote: '.$exception->getMessage(), [
            'turma_ids' => $this->turmaIds,
            'template_cracha_id' => $this->templateCrachaId,
            'user_id' => $this->userId,
        ]);

        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $user->notify(new SystemNotification(
            title: 'Falha ao processar os crachás',
            body: 'Ocorreu um erro inesperado ao gerar os crachás em lote. Por favor, tente novamente.',
            type: 'danger',
        ));
    }
}
