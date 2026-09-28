<?php

namespace App\Http\Controllers;

use App\Models\SolicitacaoDocumento;
use Illuminate\Http\Request;

class ValidarDocumentoController extends Controller
{
    /**
     * Exibe a página de validação pública de autenticidade documental.
     */
    public function __invoke(Request $request, ?string $codigo = null)
    {
        $codigoBusca = $codigo ?: $request->input('codigo');
        $documento = null;
        $buscou = false;

        if (! empty($codigoBusca)) {
            $buscou = true;
            $codigoLimpo = strtoupper(trim($codigoBusca));

            $documento = SolicitacaoDocumento::query()
                ->where('codigo_verificacao', $codigoLimpo)
                ->orWhere('protocolo', $codigoLimpo)
                ->with(['matricula.pessoa', 'matricula.turma.serie.curso.unidade', 'templateDocumento'])
                ->first();
        }

        return view('documentos.validar', [
            'documento' => $documento,
            'codigoBusca' => $codigoBusca,
            'buscou' => $buscou,
        ]);
    }
}
