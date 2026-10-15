<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PlanoAula;
use App\Models\PlanoAulaAdaptacao;
use App\Services\CopilotoPedagogico\CopilotoPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CopilotoPdfController extends Controller
{
    public function __construct(
        protected CopilotoPdfService $pdfService,
    ) {}

    /**
     * Download em streaming do PDF do Plano de Aula.
     */
    public function downloadPlanoAula(PlanoAula $planoAula): Response
    {
        Gate::authorize('view', $planoAula);

        $pdf = $this->pdfService->gerarPdfPlanoAula($planoAula);
        $nomeArquivo = 'plano_aula_'.$planoAula->id.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
        ]);
    }

    /**
     * Download em streaming do PDF da Adaptação PEI (Confidencial).
     */
    public function downloadPei(PlanoAulaAdaptacao $adaptacao): Response
    {
        $planoAula = $adaptacao->planoAula;
        Gate::authorize('exportarPei', $adaptacao);

        $pdf = $this->pdfService->gerarPdfPei($adaptacao);
        $nomeArquivo = 'pei_adaptacao_matricula_'.$adaptacao->matricula_id.'_plano_'.$planoAula->id.'.pdf';

        // Auditoria estrita sem gravar conteúdo confidencial
        activity('plano_aula')
            ->performedOn($planoAula)
            ->causedBy(auth()->user())
            ->withProperties([
                'plano_aula_adaptacao_id' => $adaptacao->id,
                'matricula_id' => $adaptacao->matricula_id,
                'acao' => 'exportar_pdf_pei',
            ])
            ->log("Exportação de PDF de adaptação curricular PEI para a matrícula #{$adaptacao->matricula_id}.");

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
        ]);
    }
}
