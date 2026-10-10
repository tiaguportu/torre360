<?php

namespace App\Http\Controllers\Comunicacao;

use App\Http\Controllers\Controller;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\Pessoa;
use App\Models\TipoContatoInteressado;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Descadastro dos e-mails comerciais da régua de follow-up. O link vem assinado no e-mail (rota `signed`):
 * sem a assinatura ninguém descadastra um número de pessoa qualquer.
 *
 * O `GET` só mostra a confirmação (scanners de e-mail abrem links sem que a pessoa tenha pedido); quem cancela é o
 * `POST`, que também atende o "cancelar com um clique" dos provedores (`List-Unsubscribe-Post`, sem token CSRF).
 */
class DescadastroController extends Controller
{
    public function show(Pessoa $pessoa): View
    {
        return view('comunicacao.descadastro', [
            'primeiroNome' => explode(' ', trim((string) $pessoa->nome))[0] ?: null,
            'ja_descadastrada' => $pessoa->aceita_comunicacao === false,
            'concluido' => false,
        ]);
    }

    public function store(Request $request, Pessoa $pessoa): View
    {
        if ($pessoa->aceita_comunicacao !== false) {
            $pessoa->descadastrarDeComunicacoes();
            $this->registrarNosLeads($pessoa);
        }

        return view('comunicacao.descadastro', [
            'primeiroNome' => explode(' ', trim((string) $pessoa->nome))[0] ?: null,
            'ja_descadastrada' => false,
            'concluido' => true,
        ]);
    }

    /**
     * Deixa na linha do tempo dos leads da pessoa o motivo de a régua ter parado de enviar e-mails. Registro
     * automático: não conta como contato com a família.
     */
    private function registrarNosLeads(Pessoa $pessoa): void
    {
        $tipo = TipoContatoInteressado::porNome('Descadastro de e-mails');

        Interessado::query()->where('pessoa_id', $pessoa->id)->get()->each(
            fn (Interessado $lead) => HistoricoContato::create([
                'interessado_id' => $lead->id,
                'tipo_contato_interessado_id' => $tipo->id,
                'data_contato' => now(),
                'usuario_id' => null,
                'relato' => 'O responsável pediu para não receber mais e-mails da escola (link de descadastro). A régua de follow-up e as comunicações em massa deixam de enviar e-mail para ele.',
                'automatico' => true,
            ])
        );
    }
}
