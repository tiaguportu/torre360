<?php

namespace App\Http\Controllers\Comercial;

use App\Enums\StatusVisitaInteressado;
use App\Http\Controllers\Controller;
use App\Models\Serie;
use App\Models\Unidade;
use App\Rules\RecaptchaV3;
use App\Services\AgendaVisitaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Página pública de agendamento de visitas (Onda 86): a família escolhe unidade, dia e horário entre os que os consultores
 * publicaram, informa os dados e recebe a confirmação por e-mail com os links para trocar ou cancelar.
 */
class VisitaPublicaController extends Controller
{
    public function __construct(private readonly AgendaVisitaService $agenda) {}

    public function agendar(Request $request): View
    {
        $unidades = Unidade::emAtividade()->orderBy('nome')->get();
        $unidadeId = $request->integer('unidade') ?: ($unidades->count() === 1 ? $unidades->first()->id : null);

        return view('visita.agendar', [
            'unidades' => $unidades,
            'unidadeId' => $unidadeId,
            'horarios' => $unidadeId ? $this->agenda->horariosLivres($unidadeId) : [],
            'series' => Serie::with('curso')->orderBy('nome')->get(),
        ]);
    }

    public function reservar(Request $request): RedirectResponse
    {
        // Armadilha para robô: o campo fica fora da tela.
        if (filled($request->input('website'))) {
            return redirect()->route('visita.publica.agendar');
        }

        $chave = 'visita:agendar:'.hash('sha256', (string) $request->ip().config('app.key'));

        if (RateLimiter::tooManyAttempts($chave, (int) config('visitas.max_agendamentos', 5))) {
            return back()->withInput()->withErrors(['data_hora' => 'Muitas marcações em pouco tempo. Tente novamente mais tarde.']);
        }

        $dados = $request->validate([
            'unidade_id' => ['required', 'integer', Rule::exists('unidade', 'id')],
            'data_hora' => ['required', 'date_format:Y-m-d H:i'],
            'responsavel_nome' => ['required', 'string', 'max:255'],
            'responsavel_email' => ['required', 'email', 'max:255'],
            'responsavel_telefone' => ['required', 'string', 'max:30'],
            'aluno_nome' => ['required', 'string', 'max:255'],
            'serie_id' => ['nullable', 'integer', Rule::exists('serie', 'id')],
            'turno_preferencia' => ['nullable', 'in:Manhã,Tarde,Integral,Sem preferência'],
            'consentimento' => ['accepted'],
            'recaptcha_token' => [new RecaptchaV3($request->ip())],
        ], [
            'responsavel_nome.required' => 'Informe o seu nome.',
            'responsavel_email.required' => 'O e-mail é obrigatório: é para ele que enviamos a confirmação.',
            'responsavel_email.email' => 'Informe um e-mail válido.',
            'responsavel_telefone.required' => 'Informe um telefone ou WhatsApp para contato.',
            'aluno_nome.required' => 'Informe o nome do estudante.',
            'data_hora.required' => 'Escolha um dia e horário.',
            'consentimento.accepted' => 'Para marcar, é preciso autorizar o uso dos dados para o atendimento de admissão.',
        ]);

        try {
            $visita = $this->agenda->reservar($dados, $request);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['data_hora' => $e->getMessage()]);
        }

        RateLimiter::hit($chave, (int) config('visitas.janela_minutos', 60) * 60);

        return redirect()->route('visita.publica.mostrar', ['token' => $visita->token_agendamento])->with('visita_marcada', true);
    }

    public function mostrar(string $token): View
    {
        return view('visita.confirmacao', ['visita' => $this->visitaOuNaoEncontrada($token)]);
    }

    public function cancelar(string $token): RedirectResponse
    {
        $visita = $this->visitaOuNaoEncontrada($token);

        try {
            $this->agenda->cancelar($visita);
        } catch (\DomainException $e) {
            return back()->withErrors(['visita' => $e->getMessage()]);
        }

        return redirect()->route('visita.publica.mostrar', ['token' => $token]);
    }

    public function reagendarForm(string $token): View|RedirectResponse
    {
        $visita = $this->visitaOuNaoEncontrada($token);

        if ($visita->status !== StatusVisitaInteressado::Agendada) {
            return redirect()->route('visita.publica.mostrar', ['token' => $token]);
        }

        return view('visita.reagendar', ['visita' => $visita, 'horarios' => $this->agenda->horariosLivres($visita->unidade_id)]);
    }

    public function reagendar(Request $request, string $token): RedirectResponse
    {
        $visita = $this->visitaOuNaoEncontrada($token);

        $dados = $request->validate(['data_hora' => ['required', 'date_format:Y-m-d H:i']], ['data_hora.required' => 'Escolha um novo dia e horário.']);

        try {
            $this->agenda->reagendar($visita, $dados['data_hora']);
        } catch (\DomainException $e) {
            return back()->withErrors(['data_hora' => $e->getMessage()]);
        }

        return redirect()->route('visita.publica.mostrar', ['token' => $token])->with('visita_reagendada', true);
    }

    private function visitaOuNaoEncontrada(string $token)
    {
        $visita = $this->agenda->buscarPorToken($token);

        abort_unless($visita !== null, 404);

        return $visita;
    }
}
