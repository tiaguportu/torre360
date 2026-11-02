<?php

namespace App\Http\Controllers\Ouvidoria;

use App\Enums\CategoriaDenuncia;
use App\Http\Controllers\Controller;
use App\Services\OuvidoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Canal público da ouvidoria (Ondas 35 e 63): formulário, protocolo com código de acompanhamento e consulta sem login.
 *
 * Nada aqui grava IP, agente de navegador ou usuário numa denúncia anônima. O limite de envios usa o IP só como chave de
 * cache (com hash), que expira sozinha. Não há reCAPTCHA de propósito: ele enviaria o endereço de quem denuncia a um terceiro.
 */
class OuvidoriaPublicaController extends Controller
{
    public function __construct(private readonly OuvidoriaService $servico) {}

    public function formulario(): View
    {
        return view('ouvidoria.formulario', ['categorias' => CategoriaDenuncia::cases()]);
    }

    public function enviar(Request $request): RedirectResponse
    {
        // Armadilha para robô: o campo fica fora da tela. Responde como se tivesse dado certo, sem gravar.
        if (filled($request->input('website'))) {
            return redirect()->route('ouvidoria.formulario');
        }

        $chave = $this->chaveDeLimite($request, 'envio');

        if (RateLimiter::tooManyAttempts($chave, (int) config('ouvidoria.max_envios', 5))) {
            return back()->withInput()->withErrors(['descricao' => 'Muitos envios em pouco tempo. Tente novamente mais tarde.']);
        }

        $dados = $request->validate([
            'categoria' => ['required', Rule::enum(CategoriaDenuncia::class)],
            'descricao' => ['required', 'string', 'min:20', 'max:5000'],
            'local_data' => ['nullable', 'string', 'max:255'],
            'envolvidos' => ['nullable', 'array', 'max:5'],
            'envolvidos.*.nome' => ['nullable', 'string', 'max:120'],
            'envolvidos.*.papel' => ['nullable', Rule::in(['vitima', 'autor', 'testemunha', 'outro'])],
            'anonima' => ['required', 'boolean'],
            'nome' => ['nullable', 'string', 'max:120'],
            'contato' => ['nullable', 'string', 'max:120'],
        ]);

        $anonima = (bool) $dados['anonima'];

        if (! $anonima && (blank($dados['nome'] ?? null) || blank($dados['contato'] ?? null))) {
            return back()->withInput()->withErrors(['nome' => 'Para enviar identificada, informe seu nome e um e-mail ou telefone para contato.']);
        }

        RateLimiter::hit($chave, (int) config('ouvidoria.janela_minutos', 30) * 60);

        $resultado = $this->servico->registrar(
            $dados,
            $anonima,
            $anonima ? null : ['nome' => $dados['nome'], 'contato' => $dados['contato']],
            $anonima ? null : $request->user(),
        );

        // O código só existe aqui: vai para a próxima tela por sessão (nunca na URL) e não é guardado em lugar nenhum.
        return redirect()->route('ouvidoria.enviada')->with('ouvidoria', [
            'protocolo' => $resultado['denuncia']->protocolo,
            'codigo' => $resultado['codigo'],
            'prazo' => $resultado['denuncia']->prazo_em?->format('d/m/Y'),
        ]);
    }

    public function enviada(Request $request): View|RedirectResponse
    {
        $resultado = $request->session()->get('ouvidoria');

        if (! $resultado) {
            return redirect()->route('ouvidoria.formulario');
        }

        return view('ouvidoria.enviada', $resultado);
    }

    public function acompanhar(): View
    {
        return view('ouvidoria.acompanhar');
    }

    public function consultar(Request $request): View
    {
        $dados = $request->validate([
            'protocolo' => ['required', 'string', 'max:32'],
            'codigo' => ['required', 'string', 'max:16'],
        ]);

        $chave = $this->chaveDeLimite($request, 'consulta');

        if (RateLimiter::tooManyAttempts($chave, (int) config('ouvidoria.max_consultas', 10))) {
            return view('ouvidoria.acompanhar', ['erro' => 'Muitas tentativas. Aguarde alguns minutos.']);
        }

        $denuncia = $this->servico->consultar($dados['protocolo'], $dados['codigo']);

        if (! $denuncia) {
            RateLimiter::hit($chave, 15 * 60);

            return view('ouvidoria.acompanhar', ['erro' => 'Protocolo ou código não conferem.']);
        }

        return view('ouvidoria.acompanhar', [
            'denuncia' => $denuncia,
            'codigo' => $dados['codigo'],
            'movimentacoes' => $this->servico->movimentacoesVisiveisAoAutor($denuncia),
        ]);
    }

    public function complementar(Request $request): View
    {
        $dados = $request->validate([
            'protocolo' => ['required', 'string', 'max:32'],
            'codigo' => ['required', 'string', 'max:16'],
            'texto' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $denuncia = $this->servico->consultar($dados['protocolo'], $dados['codigo']);

        if (! $denuncia) {
            return view('ouvidoria.acompanhar', ['erro' => 'Protocolo ou código não conferem.']);
        }

        try {
            $this->servico->complementarPeloAutor($denuncia, $dados['texto']);
        } catch (\DomainException $e) {
            return view('ouvidoria.acompanhar', ['erro' => $e->getMessage()]);
        }

        return view('ouvidoria.acompanhar', [
            'denuncia' => $denuncia->refresh(),
            'codigo' => $dados['codigo'],
            'movimentacoes' => $this->servico->movimentacoesVisiveisAoAutor($denuncia),
        ]);
    }

    private function chaveDeLimite(Request $request, string $acao): string
    {
        return 'ouvidoria:'.$acao.':'.hash('sha256', (string) $request->ip().config('app.key'));
    }
}
