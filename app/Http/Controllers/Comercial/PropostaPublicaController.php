<?php

namespace App\Http\Controllers\Comercial;

use App\Enums\StatusPropostaComercial;
use App\Http\Controllers\Controller;
use App\Models\PropostaComercial;
use App\Services\PropostaAceiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página pública da proposta comercial (Onda 87): a família lê as condições e aceita. O link tem 48 caracteres aleatórios, não
 * é indexável e só abre propostas já aprovadas. O aceite grava data, IP e o nome digitado.
 */
class PropostaPublicaController extends Controller
{
    public function __construct(private readonly PropostaAceiteService $servico) {}

    public function show(string $token): View
    {
        $proposta = $this->propostaOuNaoEncontrada($token);

        $this->servico->registrarVisualizacao($proposta);

        return view('proposta.publica', ['proposta' => $proposta->load(['unidade', 'curso', 'serie', 'turno'])]);
    }

    public function aceitar(Request $request, string $token): RedirectResponse
    {
        $proposta = $this->propostaOuNaoEncontrada($token);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:5', 'max:120'],
            'concordo' => ['accepted'],
        ], [
            'nome.required' => 'Digite o seu nome completo.',
            'nome.min' => 'Digite o seu nome completo.',
            'concordo.accepted' => 'Marque a confirmação para aceitar.',
        ]);

        try {
            $this->servico->aceitar($proposta, $dados['nome'], $request->ip());
        } catch (\DomainException $e) {
            return back()->withErrors(['nome' => $e->getMessage()]);
        }

        return redirect()->route('proposta.publica.show', ['token' => $token])->with('aceite_ok', true);
    }

    private function propostaOuNaoEncontrada(string $token): PropostaComercial
    {
        $proposta = $this->servico->buscarPorToken($token);

        // Rascunho, recusada, cancelada e em aprovação não têm página: 404 igual ao de um link que não existe.
        abort_unless($proposta && $proposta->visivelPelaFamilia(), 404);

        return $proposta;
    }

    public static function situacaoParaTexto(PropostaComercial $proposta): string
    {
        return match ($proposta->status) {
            StatusPropostaComercial::AceitaPelaFamilia => 'aceita',
            StatusPropostaComercial::Convertida => 'convertida',
            StatusPropostaComercial::Expirada => 'expirada',
            default => $proposta->aceitavel() ? 'aberta' : 'expirada',
        };
    }
}
