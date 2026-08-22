<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use App\Services\BoletimService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BoletimPDFController extends Controller
{
    public function download(Matricula $record, Request $request)
    {
        abort_unless($record->isAccessibleBy($request->user()), 403);

        $etapaId = $request->query('etapa_id');
        $boletimService = app(BoletimService::class);

        $dados = $boletimService->getDadosBoletim($record, $etapaId ?: null);

        if (empty($dados['etapas'])) {
            return back()->with('error', 'Não há dados para gerar o boletim solicitado.');
        }

        $pdf = Pdf::loadView('pdfs.boletim', $dados)
            ->setPaper('a4', 'portrait');

        $filename = 'Boletim_'.$record->pessoa->nome.'.pdf';

        return $pdf->download($filename);
    }

    public function downloadTurmas(Request $request)
    {
        $turmaIds = $request->query('turma_ids');
        if (empty($turmaIds)) {
            return back()->with('error', 'Nenhuma turma foi selecionada.');
        }

        if (! is_array($turmaIds)) {
            $turmaIds = explode(',', (string) $turmaIds);
        }

        $etapaId = $request->query('etapa_id');
        $etapaId = ($etapaId && $etapaId !== '0' && $etapaId !== 0) ? (int) $etapaId : null;

        $boletimService = app(BoletimService::class);
        $boletins = [];

        $matriculas = Matricula::query()
            ->whereIn('turma_id', $turmaIds)
            ->where('situacao', SituacaoMatricula::ATIVA)
            ->with(['pessoa', 'turma.serie.curso.unidade.instituicaoEnsino', 'turma.periodoLetivo', 'periodoLetivo'])
            ->get();

        foreach ($matriculas as $matricula) {
            $dados = $boletimService->getDadosBoletim($matricula, $etapaId);
            if (! empty($dados['etapas'])) {
                $boletins[] = $dados;
            }
        }

        if (empty($boletins)) {
            return back()->with('error', 'Não há dados para gerar os boletins solicitados.');
        }

        $pdf = Pdf::loadView('pdfs.boletins_turma', ['boletins' => $boletins])
            ->setPaper('a4', 'portrait');

        $filename = 'Boletins_Consolidados_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }
}
