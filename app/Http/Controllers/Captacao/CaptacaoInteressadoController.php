<?php

namespace App\Http\Controllers\Captacao;

use App\Enums\Sexo;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Captacao\ConfirmarConviteMatriculaRequest;
use App\Http\Requests\Captacao\StoreCaptacaoInteressadoRequest;
use App\Mail\AgradecimentoInteresseMail;
use App\Models\EmailLog;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CaptacaoInteressadoService;
use App\Services\ConviteMatriculaService;
use App\Services\IndicacaoCaptacaoService;
use App\Services\UtmTracker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class CaptacaoInteressadoController extends Controller
{
    public function __construct(private readonly IndicacaoCaptacaoService $indicacoes) {}

    public function show(Request $request): View
    {
        UtmTracker::capturar($request);
        $this->indicacoes->capturar($request);

        $unidades = Unidade::orderBy('nome')->get();

        $series = Serie::with('curso')
            ->orderBy('nome')
            ->get();

        $turmas = Turma::with(['serie.curso', 'turno'])
            ->orderBy('nome')
            ->get();

        $origens = OrigemInteressado::orderBy('nome')->get();

        return view('captacao.interessado', compact('unidades', 'series', 'turmas', 'origens'));
    }

    public function store(StoreCaptacaoInteressadoRequest $request, CaptacaoInteressadoService $captacao): RedirectResponse
    {
        $validated = $request->validated();
        $resultado = $captacao->registrar($validated, $request);

        // Na página de agradecimento vai o nome como a pessoa acabou de digitar, não o já gravado no cadastro.
        $nomeInformado = $validated['tipo_preenchimento'] === 'responsavel'
            ? $validated['responsavel_nome']
            : $validated['alunos'][0]['nome'];

        $pessoa = $resultado['pessoa'];
        $primeiraUnidadeId = $resultado['primeira_unidade_id'];

        $this->enviarEmailERegistrarLog($pessoa, $primeiraUnidadeId);

        $this->notificarEquipeInterna($resultado);

        // Redireciona com dados para personalizar a página de sucesso
        $primeiraUnidade = $primeiraUnidadeId ? Unidade::find($primeiraUnidadeId) : Unidade::where('flag_ativo', true)->first();

        return redirect()
            ->route('captacao.interessado.sucesso')
            ->with([
                'nome_responsavel' => $nomeInformado,
                'whatsapp_unidade' => $primeiraUnidade?->celular_whatsapp ?? null,
                'nome_unidade' => $primeiraUnidade?->nome ?? null,
            ]);
    }

    /**
     * Notifica a equipe (e o consultor do lead, se houver) sobre o novo lead ou sobre o retorno de um lead existente.
     *
     * @param  array{interessado: Interessado, pessoa: Pessoa, indicador: ?Pessoa, novo: bool, reaberto: bool, ja_matriculado: bool}  $resultado
     */
    private function notificarEquipeInterna(array $resultado): void
    {
        $interessado = $resultado['interessado'];
        $pessoa = $resultado['pessoa'];
        $indicador = $resultado['indicador'];

        $destinatarios = $this->destinatariosDaEquipe();

        if ($interessado->usuario_id && ! $destinatarios->contains('id', $interessado->usuario_id)) {
            $consultor = User::query()->find($interessado->usuario_id);

            if ($consultor) {
                $destinatarios->push($consultor);
            }
        }

        if ($destinatarios->isEmpty()) {
            return;
        }

        [$titulo, $corpo, $icone, $cor] = match (true) {
            $resultado['novo'] => [
                'Novo Interessado Cadastrado!',
                "**{$pessoa->nome}** acaba de preencher o formulário de interesse via site.",
                'heroicon-o-user-plus',
                'success',
            ],
            $resultado['reaberto'] => [
                'Lead perdido voltou a procurar a escola!',
                "**{$pessoa->nome}** estava como perdido e preencheu o formulário de interesse novamente. O lead foi reaberto.",
                'heroicon-o-arrow-path',
                'warning',
            ],
            $resultado['ja_matriculado'] => [
                'Família matriculada enviou novo interesse',
                "**{$pessoa->nome}**, que já é família matriculada, preencheu o formulário de interesse novamente (possível novo aluno).",
                'heroicon-o-academic-cap',
                'info',
            ],
            default => [
                'Interessado reenviou o formulário',
                "**{$pessoa->nome}** preencheu o formulário de interesse novamente. Confira os dados novos na linha do tempo.",
                'heroicon-o-arrow-uturn-left',
                'info',
            ],
        };

        if ($indicador && $resultado['novo']) {
            $corpo .= " Veio por indicação da família **{$indicador->nome}**.";
        }

        Notification::make()
            ->title($titulo)
            ->body($corpo)
            ->icon($icone)
            ->color($cor)
            ->actions([
                Action::make('view')
                    ->label('Ver Leads')
                    ->url(InteressadoResource::getUrl('edit', ['record' => $interessado]))
                    ->button(),
            ])
            ->sendToDatabase($destinatarios);
    }

    /**
     * @return Collection<int, User>
     */
    private function destinatariosDaEquipe(): Collection
    {
        // `User::permission()` lança exceção se a permissão ainda não foi criada (instalação nova): o formulário
        // público não pode cair por isso, então recorre direto aos administradores.
        try {
            $destinatarios = User::permission('View:Interessado')->get();
        } catch (PermissionDoesNotExist) {
            $destinatarios = collect();
        }

        if ($destinatarios->isEmpty()) {
            $destinatarios = User::role(['admin', 'super_admin'])->get();
        }

        return $destinatarios->values();
    }

    /**
     * Envia e-mail de agradecimento e registra no log. No máximo um por pessoa na janela configurada: o
     * formulário é público e sem login, então sem isso serviria para encher a caixa de entrada de terceiros.
     */
    private function enviarEmailERegistrarLog(Pessoa $pessoa, ?int $unidadeId = null): void
    {
        if (! $pessoa->email) {
            return;
        }

        $janelaHoras = (int) config('crm.captacao.agradecimento_janela_horas', 24);

        if (! Cache::add("captacao:agradecimento:{$pessoa->id}", true, now()->addHours($janelaHoras))) {
            return;
        }

        $unidade = ($unidadeId ? Unidade::find($unidadeId) : null) ?? Unidade::first();

        if (! $unidade) {
            $unidade = new Unidade(['nome' => 'Torre360']);
        }

        try {
            $mailable = new AgradecimentoInteresseMail($pessoa, $unidade);
            Mail::to($pessoa->email)->send($mailable);

            EmailLog::create([
                'to' => [$pessoa->email],
                'subject' => "Recebemos seu interesse - {$unidade->nome}",
                'body' => (string) $mailable->render(),
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Falha ao enviar e-mail de agradecimento para {$pessoa->email}: ".$e->getMessage());
        }
    }

    public function sucesso(): View
    {
        return view('captacao.sucesso', [
            'nomeResponsavel' => session('nome_responsavel'),
            'whatsappUnidade' => session('whatsapp_unidade'),
            'nomeUnidade' => session('nome_unidade'),
        ]);
    }

    /**
     * Página do convite de matrícula online: confirma/completa os dados de um lead já
     * qualificado pelo CRM, pré-preenchidos e restritos ao próprio interessado — nenhum
     * outro registro é exposto nem navegável a partir daqui.
     */
    public function convite(string $token, ConviteMatriculaService $service): View
    {
        $interessado = $service->validarToken($token);

        if (! $interessado) {
            return view('captacao.convite-invalido');
        }

        $interessado->loadMissing(['pessoa', 'dependentes.serie']);

        return view('captacao.convite', [
            'interessado' => $interessado,
            'series' => Serie::with('curso')->orderBy('nome')->get(),
            'tiposVinculo' => TipoVinculo::orderBy('nome')->pluck('nome', 'id'),
            'sexos' => Sexo::cases(),
            'dados' => $interessado->dados_pre_matricula ?? [],
        ]);
    }

    public function confirmarConvite(ConfirmarConviteMatriculaRequest $request, string $token, ConviteMatriculaService $service): RedirectResponse
    {
        $interessado = $request->getInteressado();

        if (! $interessado) {
            return redirect()->route('captacao.interessado.convite', $token);
        }

        $validated = $request->validated();
        $responsavel = $validated['responsavel'];

        $service->confirmar(
            $interessado,
            ['telefone' => $responsavel['telefone'], 'email' => $responsavel['email'] ?? null],
            $validated['dependentes'],
            $service->montarPreMatricula($validated, $request->ip())
        );

        return redirect()->route('captacao.interessado.convite.sucesso', $token);
    }

    public function conviteConfirmado(string $token): View
    {
        return view('captacao.convite-sucesso');
    }
}
