<?php

namespace App\Http\Controllers\Financeiro;

use App\Enums\StatusAcordoInadimplencia;
use App\Http\Controllers\Controller;
use App\Models\AcordoInadimplencia;
use App\Services\AcordoInadimplenciaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcordoPublicoController extends Controller
{
    public function show(string $token): View
    {
        $acordo = AcordoInadimplencia::with(['matricula.pessoa', 'responsavelPessoa', 'parcelas'])
            ->where('token_publico', $token)
            ->firstOrFail();

        return view('financeiro.acordo-publico', [
            'acordo' => $acordo,
        ]);
    }

    public function aceitar(Request $request, string $token, AcordoInadimplenciaService $service)
    {
        $acordo = AcordoInadimplencia::where('token_publico', $token)->firstOrFail();

        if ($acordo->status !== StatusAcordoInadimplencia::AguardandoAceite && $acordo->status !== StatusAcordoInadimplencia::Simulado) {
            return redirect()->route('acordo.publico.show', ['token' => $token])
                ->with('mensagem_erro', 'Este acordo já foi homologado ou não está mais disponível para aceite online.');
        }

        $service->confirmarAceite(
            $acordo,
            $request->ip(),
            $request->userAgent()
        );

        return redirect()->route('acordo.publico.show', ['token' => $token])
            ->with('mensagem_sucesso', 'Acordo aceito com sucesso! O cronograma de pagamentos foi confirmado e o termo de confissão de dívida foi lavrado.');
    }
}
