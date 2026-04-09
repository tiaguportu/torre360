<?php

namespace App\Http\Controllers\Contratos;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use Illuminate\Http\Request;

class VisualizarContratoController extends Controller
{
    public function __invoke(Contrato $contrato)
    {
        $contrato->load(['matriculas.pessoa', 'matriculas.turma.serie.curso', 'matriculas.periodoLetivo', 'responsaveisFinanceiros.pessoa']);
        
        $matricula = $contrato->matriculas->first();
        $aluno = $matricula?->pessoa;
        $responsavel = $contrato->responsaveisFinanceiros->first()?->pessoa;

        return view('contratos.visualizar', [
            'contrato' => $contrato,
            'matricula' => $matricula,
            'aluno' => $aluno,
            'responsavel' => $responsavel,
            'serie' => $matricula?->turma?->serie,
            'curso' => $matricula?->turma?->serie?->curso,
            'periodoLetivo' => $matricula?->periodoLetivo,
        ]);
    }
}
