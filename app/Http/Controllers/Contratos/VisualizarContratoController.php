<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\TemplateContrato;
use App\Services\ContractTemplateService;

class VisualizarContratoController extends Controller
{
    public function __invoke(Contrato $contrato)
    {
        $contrato->load(['matricula.pessoa', 'matricula.turma.serie.curso', 'matricula.periodoLetivo', 'responsaveisFinanceiros.pessoa', 'templateContrato']);

        $matricula = $contrato->matricula;
        $aluno = $matricula?->pessoa;
        $responsavel = $contrato->responsaveisFinanceiros->first()?->pessoa;

        // Lógica de Template Dinâmico
        $template = $contrato->templateContrato
            ?? TemplateContrato::where('is_padrao', true)->first();

        $conteudoTemplate = null;
        $cabecalhoTemplate = null;
        $rodapeTemplate = null;
        if ($template) {
            $templateService = app(ContractTemplateService::class);
            $conteudoTemplate = $templateService->process($contrato, $template->conteudo);
            $cabecalhoTemplate = $template->cabecalho ? $templateService->process($contrato, $template->cabecalho) : null;
            $rodapeTemplate = $template->rodape ? $templateService->process($contrato, $template->rodape) : null;
        }

        return view('contratos.visualizar', [
            'contrato' => $contrato,
            'matricula' => $matricula,
            'matriculas' => $matricula ? collect([$matricula]) : collect(),
            'aluno' => $aluno,
            'responsavel' => $responsavel,
            'serie' => $matricula?->turma?->serie,
            'curso' => $matricula?->turma?->serie?->curso,
            'periodoLetivo' => $matricula?->periodoLetivo,
            'conteudo_template' => $conteudoTemplate,
            'cabecalho_template' => $cabecalhoTemplate,
            'rodape_template' => $rodapeTemplate,
        ]);
    }
}
