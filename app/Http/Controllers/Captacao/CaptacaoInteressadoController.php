<?php

namespace App\Http\Controllers\Captacao;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Http\Controllers\Controller;
use App\Mail\AgradecimentoInteresseMail;
use App\Models\EmailLog;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\LeadScoreService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CaptacaoInteressadoController extends Controller
{
    public function show(): View
    {
        $unidades = Unidade::orderBy('nome')->get();

        $series = Serie::with('curso')
            ->orderBy('nome')
            ->get();

        $turmas = Turma::with(['serie.curso', 'turno'])
            ->orderBy('nome')
            ->get();

        return view('captacao.interessado', compact('unidades', 'series', 'turmas'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Verifica reCAPTCHA v3
        $this->verificarRecaptcha($request);

        $validated = $request->validate([
            // Quem preenche
            'tipo_preenchimento' => ['required', 'in:proprio,responsavel'],

            // Dados do responsável
            'responsavel_nome' => ['required_if:tipo_preenchimento,responsavel', 'nullable', 'string', 'max:255'],
            'responsavel_cpf' => ['nullable', 'string', 'max:20'],
            'responsavel_telefone' => ['required', 'string', 'max:30'],
            'responsavel_email' => ['required', 'email', 'max:255'],

            // Múltiplos alunos
            'alunos' => ['required', 'array', 'min:1'],
            'alunos.*.nome' => ['required', 'string', 'max:255'],
            'alunos.*.data_nascimento' => ['nullable', 'date'],
            'alunos.*.vinculo' => ['nullable', 'string', 'max:100'],
            'alunos.*.unidade_id' => ['nullable', 'exists:unidade,id'],
            'alunos.*.serie_id' => ['nullable', 'exists:serie,id'],
            'alunos.*.turno_preferencia' => ['nullable', 'string', 'in:Manhã,Tarde,Integral,Sem preferência'],

            // Extras
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'como_conheceu' => ['nullable', 'exists:origem_interessado,id'],
        ], [
            'tipo_preenchimento.required' => 'Informe quem está preenchendo.',
            'responsavel_nome.required_if' => 'Informe o nome do responsável.',
            'responsavel_telefone.required' => 'O telefone / WhatsApp para contato é obrigatório.',
            'responsavel_email.required' => 'O e-mail para contato é obrigatório.',
            'responsavel_email.email' => 'Informe um e-mail válido.',
            'alunos.required' => 'Informe os dados de ao menos um aluno.',
            'alunos.*.nome.required' => 'Informe o nome completo do aluno.',
        ]);

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

        foreach ($alunos as $alunoData) {
            if (empty($alunoData['nome'])) {
                continue;
            }

            // Prioriza serie_id do formulário novo, fallback para turma (legado)
            $serieId = $alunoData['serie_id'] ?? null;

            if (! $serieId && ! empty($alunoData['turma_id'])) {
                $turma = Turma::find($alunoData['turma_id']);
                $serieId = $turma?->serie_id;
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
            \Log::error("Falha ao enviar e-mail de agradecimento para {$pessoa->email}: ".$e->getMessage());
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
     * Monta texto de observações consolidando os dados do formulário.
     *
     * @param  array<string, mixed>  $data
     */
    private function montarObservacoes(array $data): string
    {
        $obs = [];

        foreach ($data['alunos'] ?? [] as $i => $aluno) {
            $label = 'Aluno '.($i + 1).': '.($aluno['nome'] ?? '-');

            if (! empty($aluno['unidade_id'])) {
                $label .= ' | Unidade: '.(Unidade::find($aluno['unidade_id'])?->nome ?? '-');
            }

            if (! empty($aluno['serie_id'])) {
                $label .= ' | Série: '.(Serie::find($aluno['serie_id'])?->nome ?? '-');
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

    /**
     * Verifica o token reCAPTCHA v3 com a API do Google.
     */
    private function verificarRecaptcha(Request $request): void
    {
        $siteKey = config('services.recaptcha.site_key');
        $secret = config('services.recaptcha.secret');

        if (empty($siteKey) || empty($secret)) {
            \Log::info('reCAPTCHA ignorado: Chaves não configuradas no .env');

            return;
        }

        $token = $request->input('recaptcha_token');

        if (empty($token)) {
            if (! empty($siteKey) && ! empty($secret)) {
                \Log::warning('reCAPTCHA falhou: Token ausente no request com chaves configuradas');
                abort(422, 'Verificação de segurança ausente. Por favor, tente novamente.');
            }

            \Log::info('reCAPTCHA ignorado: Token ausente e chaves não configuradas');

            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            $result = $response->json();

            if (! ($result['success'] ?? false) || ($result['score'] ?? 0) < 0.3) {
                \Log::warning('reCAPTCHA falhou: Score baixo ou erro na API', ['result' => $result]);
                abort(422, 'O sistema detectou uma atividade suspeita. Por favor, tente preencher o formulário novamente.');
            }
        } catch (\Exception $e) {
            \Log::error('Erro ao conectar com API do reCAPTCHA: '.$e->getMessage());
        }
    }
}
