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
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ConviteMatriculaService;
use App\Services\LeadScoreService;
use App\Services\UtmTracker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CaptacaoInteressadoController extends Controller
{
    public function show(Request $request): View
    {
        UtmTracker::capturar($request);

        $unidades = Unidade::orderBy('nome')->get();

        $series = Serie::with('curso')
            ->orderBy('nome')
            ->get();

        $turmas = Turma::with(['serie.curso', 'turno'])
            ->orderBy('nome')
            ->get();

        return view('captacao.interessado', compact('unidades', 'series', 'turmas'));
    }

    public function store(StoreCaptacaoInteressadoRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $nomeInteressado = $validated['tipo_preenchimento'] === 'responsavel'
            ? $validated['responsavel_nome']
            : $validated['alunos'][0]['nome'];

        // Cria ou localiza a Pessoa pelo e-mail
        $pessoa = Pessoa::firstOrCreate(
            ['email' => $validated['responsavel_email']],
            [
                'nome' => $nomeInteressado,
                'cpf' => $validated['responsavel_cpf'] ?? null,
                'telefone' => $validated['responsavel_telefone'],
            ]
        );

        $statusNovo = StatusInteressado::where('nome', 'Novo')->first();
        $origemSite = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        $origemId = $request->como_conheceu ?? $origemSite->id;

        $interessado = Interessado::updateOrCreate(
            ['pessoa_id' => $pessoa->id],
            [
                'status_interessado_id' => $statusNovo?->id ?? 1,
                'origem_interessado_id' => $origemId,
                'data_primeiro_contato' => now(),
                'data_proximo_contato' => now()->addDays(1),
                'observacoes' => $this->montarObservacoes($validated),
            ]
        );

        $this->registrarAtribuicao($interessado, UtmTracker::atribuicao($request));

        $this->salvarDependentes($interessado, $validated);

        LeadScoreService::recalcular($interessado);

        $primeiraUnidadeId = $validated['alunos'][0]['unidade_id'] ?? null;
        $this->enviarEmailERegistrarLog($pessoa, $primeiraUnidadeId);

        $this->notificarEquipeInterna($interessado, $pessoa);

        // Redireciona com dados para personalizar a página de sucesso
        $primeiraUnidade = $primeiraUnidadeId ? Unidade::find($primeiraUnidadeId) : Unidade::where('flag_ativo', true)->first();

        return redirect()
            ->route('captacao.interessado.sucesso')
            ->with([
                'nome_responsavel' => $nomeInteressado,
                'whatsapp_unidade' => $primeiraUnidade?->celular_whatsapp ?? null,
                'nome_unidade' => $primeiraUnidade?->nome ?? null,
            ]);
    }

    /**
     * Grava a atribuição de campanha/UTM apenas se o lead ainda não tiver uma
     * (first touch: um novo envio do mesmo contato não reescreve a origem).
     *
     * @param  array<string, mixed>  $atribuicao
     */
    private function registrarAtribuicao(Interessado $interessado, array $atribuicao): void
    {
        if ($atribuicao === [] || filled($interessado->utm_source) || filled($interessado->utm_campaign) || filled($interessado->campanha_marketing_id)) {
            return;
        }

        $interessado->update($atribuicao);
    }

    /**
     * Notifica a equipe administrativa sobre o novo lead.
     */
    private function notificarEquipeInterna(Interessado $interessado, Pessoa $pessoa): void
    {
        $destinatarios = User::permission('View:Interessado')->get();

        if ($destinatarios->isEmpty()) {
            $destinatarios = User::role(['admin', 'super_admin'])->get();
        }

        if ($destinatarios->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Novo Interessado Cadastrado!')
            ->body("**{$pessoa->nome}** acaba de preencher o formulário de interesse via site.")
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->actions([
                Action::make('view')
                    ->label('Ver Leads')
                    ->url(InteressadoResource::getUrl('index'))
                    ->button(),
            ])
            ->sendToDatabase($destinatarios);
    }

    /**
     * Salva os dependentes vinculados ao interessado.
     */
    private function salvarDependentes(Interessado $interessado, array $data): void
    {
        $interessado->dependentes()->delete();

        $alunos = $data['alunos'] ?? [];
        $turmaIds = array_values(array_filter(array_column($alunos, 'turma_id')));
        $turmasPorId = ! empty($turmaIds) ? Turma::whereIn('id', $turmaIds)->pluck('serie_id', 'id') : collect();

        foreach ($alunos as $alunoData) {
            if (empty($alunoData['nome'])) {
                continue;
            }

            // Prioriza serie_id do formulário novo, fallback para turma (legado)
            $serieId = $alunoData['serie_id'] ?? null;

            if (! $serieId && ! empty($alunoData['turma_id'])) {
                $serieId = $turmasPorId[$alunoData['turma_id']] ?? null;
            }

            InteressadoDependente::create([
                'interessado_id' => $interessado->id,
                'nome_crianca' => $alunoData['nome'],
                'data_nascimento' => $alunoData['data_nascimento'] ?? null,
                'vinculo' => $alunoData['vinculo'] ?? 'Parente',
                'serie_id' => $serieId,
            ]);
        }
    }

    /**
     * Envia e-mail de agradecimento e registra no log.
     */
    private function enviarEmailERegistrarLog(Pessoa $pessoa, ?int $unidadeId = null): void
    {
        if (! $pessoa->email) {
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

    /**
     * Monta texto de observações consolidando os dados do formulário sem N+1.
     *
     * @param  array<string, mixed>  $data
     */
    private function montarObservacoes(array $data): string
    {
        $obs = [];
        $alunos = $data['alunos'] ?? [];

        $unidadeIds = array_values(array_filter(array_column($alunos, 'unidade_id')));
        $serieIds = array_values(array_filter(array_column($alunos, 'serie_id')));

        $unidadesPorId = ! empty($unidadeIds) ? Unidade::whereIn('id', $unidadeIds)->pluck('nome', 'id') : collect();
        $seriesPorId = ! empty($serieIds) ? Serie::whereIn('id', $serieIds)->pluck('nome', 'id') : collect();

        foreach ($alunos as $i => $aluno) {
            $label = 'Aluno '.($i + 1).': '.($aluno['nome'] ?? '-');

            if (! empty($aluno['unidade_id'])) {
                $nomeUnidade = $unidadesPorId[$aluno['unidade_id']] ?? '-';
                $label .= ' | Unidade: '.$nomeUnidade;
            }

            if (! empty($aluno['serie_id'])) {
                $nomeSerie = $seriesPorId[$aluno['serie_id']] ?? '-';
                $label .= ' | Série: '.$nomeSerie;
            }

            if (! empty($aluno['turno_preferencia'])) {
                $label .= ' | Turno: '.$aluno['turno_preferencia'];
            }

            $obs[] = $label;
        }

        if (! empty($data['observacoes'])) {
            $obs[] = 'Observações: '.$data['observacoes'];
        }

        $obs[] = 'Origem: Formulário público (site)';

        return implode("\n", $obs);
    }
}
