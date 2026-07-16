<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\TemplateContrato;
use App\Services\ContractTemplateService;

class VisualizarContratoPDFController extends Controller
{
    public function __invoke(Contrato $contrato, ContractTemplateService $templateService)
    {
        $contrato->load([
            'matricula.pessoa',
            'matricula.turma.serie.curso',
            'matricula.periodoLetivo',
            'responsaveisFinanceiros.pessoa',
            'templateContrato',
        ]);

        $template = $contrato->templateContrato
            ?? TemplateContrato::where('is_padrao', true)->first();

        if (! $template) {
            abort(404, 'Nenhum template de contrato ativo no sistema.');
        }

        // Fluxo para template DomPDF
        $matricula = $contrato->matricula;
        $aluno = $matricula?->pessoa;
        $responsavel = $contrato->responsaveisFinanceiros->first()?->pessoa;

        $conteudoTemplate = $templateService->process($contrato, $template->conteudo);
        $cabecalhoTemplate = $template->cabecalho ? $templateService->process($contrato, $template->cabecalho) : null;
        $rodapeTemplate = $template->rodape ? $templateService->process($contrato, $template->rodape) : null;

        $pdf = $templateService->generatePdf([
            'contrato' => $contrato,
            'matricula' => $matricula,
            'aluno' => $aluno,
            'responsavel' => $responsavel,
            'responsaveisFinanceiros' => $contrato->responsaveisFinanceiros,
            'serie' => $matricula?->turma?->serie,
            'curso' => $matricula?->turma?->serie?->curso,
            'periodoLetivo' => $matricula?->periodoLetivo,
            'conteudo_template' => $conteudoTemplate,
            'cabecalho_template' => $cabecalhoTemplate,
            'rodape_template' => $rodapeTemplate,
        ]);

        return $pdf->stream("Contrato_{$contrato->id}.pdf");
    }
}
