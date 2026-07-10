<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\TemplateContrato;
use App\Services\ContractTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;

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

        if ($template->versao == 2) {
            try {
                $pdfContent = $templateService->generatePdfFromOdt($contrato, $template);

                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="Contrato_'.$contrato->id.'.pdf"',
                ]);
            } catch (\Exception $e) {
                logger()->error("Erro ao gerar PDF ODT para visualização do Contrato #{$contrato->id}: ".$e->getMessage());
                abort(500, 'Erro ao processar PDF do contrato: '.$e->getMessage());
            }
        }

        // Fluxo para template V1 (DomPDF)
        $matricula = $contrato->matricula;
        $aluno = $matricula?->pessoa;
        $responsavel = $contrato->responsaveisFinanceiros->first()?->pessoa;

        $conteudoTemplate = $templateService->process($contrato, $template->conteudo);
        $cabecalhoTemplate = $template->cabecalho ? $templateService->process($contrato, $template->cabecalho) : null;
        $rodapeTemplate = $template->rodape ? $templateService->process($contrato, $template->rodape) : null;

        $pdf = Pdf::loadView('pdfs.contrato', [
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
