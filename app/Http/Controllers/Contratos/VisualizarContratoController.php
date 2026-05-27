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
        $contrato->load(['matriculas.pessoa', 'matriculas.turma.serie.curso', 'matriculas.periodoLetivo', 'responsaveisFinanceiros.pessoa', 'templateContrato']);

        $matricula = $contrato->matriculas->first();
        $aluno = $matricula?->pessoa;
        $responsavel = $contrato->responsaveisFinanceiros->first()?->pessoa;

        // Lógica de Template Dinâmico
        $template = $contrato->templateContrato
            ?? TemplateContrato::where('is_padrao', true)->first();

        $conteudoTemplate = null;
        if ($template) {
            $templateService = app(ContractTemplateService::class);
            $conteudoTemplate = $templateService->process($contrato, $template->conteudo);
        }

        return view('contratos.visualizar', [
            'contrato' => $contrato,
            'matricula' => $matricula,
            'matriculas' => $contrato->matriculas,
            'aluno' => $aluno,
            'responsavel' => $responsavel,
            'serie' => $matricula?->turma?->serie,
            'curso' => $matricula?->turma?->serie?->curso,
            'periodoLetivo' => $matricula?->periodoLetivo,
            'conteudo_template' => $conteudoTemplate,
        ]);
    }
}
