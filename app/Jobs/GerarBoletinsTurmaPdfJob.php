<?php

namespace App\Jobs;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\BoletimService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GerarBoletinsTurmaPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    /**
     * @param  array<int>  $turmaIds
     */
    public function __construct(
        public array $turmaIds,
        public ?int $etapaId,
        public int $userId,
    ) {}

    public function handle(BoletimService $boletimService): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $matriculas = Matricula::query()
            ->whereIn('turma_id', $this->turmaIds)
            ->where('situacao', SituacaoMatricula::ATIVA)
            ->with(['pessoa', 'turma.serie.curso.unidade.instituicaoEnsino', 'turma.periodoLetivo', 'periodoLetivo'])
            ->get();

        $boletins = [];
        foreach ($matriculas as $matricula) {
            $dados = $boletimService->getDadosBoletim($matricula, $this->etapaId);
            if (! empty($dados['etapas'])) {
                $boletins[] = $dados;
            }
        }

        if (empty($boletins)) {
            $user->notify(new SystemNotification(
                title: 'Não foi possível gerar os boletins',
                body: 'Não há dados para gerar os boletins solicitados.',
                type: 'warning',
            ));

            return;
        }

        $pdf = Pdf::loadView('pdfs.boletins_turma', ['boletins' => $boletins])
            ->setPaper('a4', 'portrait');

        $path = 'boletins/'.$this->userId.'/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        $user->notify(new SystemNotification(
            title: 'Boletins prontos para download',
            body: 'Os boletins solicitados foram gerados com sucesso.',
            actionUrl: route('documentos.visualizar', ['path' => $path]),
            actionLabel: 'Baixar PDF',
            type: 'success',
        ));
    }
}
