<?php

namespace App\Http\Controllers;

use App\Models\Matricula;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MatriculaOnlineController extends Controller
{
    /**
     * Exibe a tela de confirmação e protocolo da Matrícula 100% Online.
     */
    public function sucesso(Request $request, Matricula $matricula): View
    {
        // Proteção contra IDOR / BOLA (OWASP A01 / LGPD):
        // Impede enumeração pública e raspagem de dados de menores e responsáveis por ID sequencial.
        $sessaoValida = session('matricula_online_id') === $matricula->id;
        $assinaturaValida = $request->hasValidSignature();
        $usuarioAutorizado = auth()->check() && $matricula->isAccessibleBy(auth()->user());

        abort_unless($sessaoValida || $assinaturaValida || $usuarioAutorizado, 403, 'Acesso não autorizado a este comprovante de matrícula.');

        $matricula->load([
            'pessoa.enderecos.cidade.estado',
            'turma.serie.curso',
            'turma.periodoLetivo',
            'contrato.responsaveisFinanceiros.pessoa',
            'documentosInseridos.tipoDocumento',
        ]);

        $aluno = $matricula->pessoa;
        $turma = $matricula->turma;
        $contrato = $matricula->contrato;
        $responsavel = $contrato?->responsaveisFinanceiros?->first()?->pessoa;

        return view('matricula-online.sucesso', [
            'matricula' => $matricula,
            'aluno' => $aluno,
            'turma' => $turma,
            'contrato' => $contrato,
            'responsavel' => $responsavel,
            'documentos' => $matricula->documentosInseridos,
        ]);
    }
}
