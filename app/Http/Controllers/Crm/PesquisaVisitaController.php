<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\HistoricoContato;
use App\Models\PesquisaSatisfacaoVisita;
use App\Models\TipoContatoInteressado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PesquisaVisitaController extends Controller
{
    /**
     * Exibe o formulário público da pesquisa de satisfação da visita escolar.
     */
    public function show(string $token): View|RedirectResponse
    {
        $pesquisa = PesquisaSatisfacaoVisita::where('token', $token)
            ->with(['visita.usuario', 'visita.dependente.serie', 'interessado.pessoa'])
            ->firstOrFail();

        if ($pesquisa->isRespondida()) {
            return redirect()->route('pesquisa-visita.sucesso', ['token' => $token]);
        }

        return view('crm.pesquisa-visita', compact('pesquisa'));
    }

    /**
     * Processa as respostas da pesquisa enviadas pela família.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $pesquisa = PesquisaSatisfacaoVisita::where('token', $token)
            ->with(['interessado', 'visita'])
            ->firstOrFail();

        if ($pesquisa->isRespondida()) {
            return redirect()->route('pesquisa-visita.sucesso', ['token' => $token]);
        }

        $dados = $request->validate([
            'nota_nps' => ['required', 'integer', 'between:0,10'],
            'nota_atendimento' => ['nullable', 'integer', 'between:1,5'],
            'nota_infraestrutura' => ['nullable', 'integer', 'between:1,5'],
            'nota_proposta_pedagogica' => ['nullable', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:1500'],
        ]);

        $pesquisa->update([
            'nota_nps' => $dados['nota_nps'],
            'nota_atendimento' => $dados['nota_atendimento'] ?? null,
            'nota_infraestrutura' => $dados['nota_infraestrutura'] ?? null,
            'nota_proposta_pedagogica' => $dados['nota_proposta_pedagogica'] ?? null,
            'comentario' => $dados['comentario'] ?? null,
            'respondido_em' => now(),
            'ip' => $request->ip(),
        ]);

        // Registra feedback na timeline do lead
        if ($pesquisa->interessado) {
            $tipoContato = TipoContatoInteressado::where('nome', 'like', '%Presencial%')
                ->orWhere('nome', 'like', '%WhatsApp%')
                ->first()
                ?? TipoContatoInteressado::firstOrCreate(
                    ['nome' => 'WhatsApp'],
                    ['is_ativo' => true]
                );

            $classificacao = $pesquisa->classificacaoNps();
            $comentarioTexto = filled($pesquisa->comentario) ? " Comentário da família: \"{$pesquisa->comentario}\"" : '';

            HistoricoContato::create([
                'interessado_id' => $pesquisa->interessado_id,
                'tipo_contato_interessado_id' => $tipoContato->id,
                'data_contato' => now(),
                'usuario_id' => null,
                'relato' => "Pesquisa de Satisfação Pós-Tour respondida pela família. NPS: {$pesquisa->nota_nps}/10 ({$classificacao}).{$comentarioTexto}",
                'resultado' => $pesquisa->nota_nps >= 7 ? 'retornar' : 'sem_interesse',
            ]);
        }

        return redirect()->route('pesquisa-visita.sucesso', ['token' => $token]);
    }

    /**
     * Tela de agradecimento após o envio da pesquisa.
     */
    public function sucesso(string $token): View
    {
        $pesquisa = PesquisaSatisfacaoVisita::where('token', $token)
            ->with(['interessado.pessoa', 'visita.usuario'])
            ->firstOrFail();

        return view('crm.pesquisa-visita-sucesso', compact('pesquisa'));
    }
}
