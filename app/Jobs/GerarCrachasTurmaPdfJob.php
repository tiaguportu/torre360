<?php

namespace App\Jobs;

use App\Enums\SituacaoMatricula;
use App\Models\TemplateCracha;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\SystemNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GerarCrachasTurmaPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

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

        $pessoasComTurma = collect();
        $turmas = Turma::whereIn('id', $this->turmaIds)->get();
        foreach ($turmas as $turma) {
            $matriculas = $turma->matriculas()
                ->where('situacao', SituacaoMatricula::ATIVA)
                ->with('pessoa')
                ->get();

            foreach ($matriculas as $matricula) {
                if ($matricula->pessoa) {
                    $pessoasComTurma->push((object) [
                        'pessoa' => $matricula->pessoa,
                        'turma' => $turma,
                    ]);
                }
            }
        }

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
}
