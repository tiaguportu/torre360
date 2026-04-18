<?php

namespace App\Http\Controllers\Captacao;

use App\Http\Controllers\Controller;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\Turma;
use App\Models\Unidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class CaptacaoInteressadoController extends Controller
{
    public function show(): View
    {
        $unidades = Unidade::where('flag_ativo', true)->orderBy('nome')->get();

        $turmas = Turma::with(['serie.curso', 'turno'])
            ->orderBy('nome')
            ->get();

        return view('captacao.interessado', compact('unidades', 'turmas'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Verifica reCAPTCHA v3
        $this->verificarRecaptcha($request);

        $validated = $request->validate([
            // Quem preenche
            'tipo_preenchimento' => ['required', 'in:proprio,responsavel'],

            // Dados do responsável (quando pai/mãe preenche)
            'responsavel_nome' => ['required_if:tipo_preenchimento,responsavel', 'nullable', 'string', 'max:255'],
            'responsavel_cpf' => ['nullable', 'string', 'max:20'],
            'responsavel_telefone' => ['required', 'string', 'max:30'],
            'responsavel_email' => ['required', 'email', 'max:255'],
            'responsavel_vinculo' => ['required_if:tipo_preenchimento,responsavel', 'nullable', 'string', 'max:100'],

            // Dados do aluno
            'aluno_nome' => ['required', 'string', 'max:255'],
            'aluno_data_nascimento' => ['nullable', 'date'],

            // Interesse
            'unidade_id' => ['nullable', 'exists:unidade,id'],
            'turma_id' => ['nullable', 'exists:turma,id'],
            'turno_preferencia' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'como_conheceu' => ['nullable', 'exists:origem_interessado,id'],
        ], [
            'tipo_preenchimento.required' => 'Informe quem está preenchendo.',
            'responsavel_nome.required_if' => 'Informe o nome do responsável.',
            'responsavel_telefone.required' => 'O telefone para contato é obrigatório.',
            'responsavel_email.required' => 'O e-mail para contato é obrigatório.',
            'responsavel_email.email' => 'Informe um e-mail válido.',
            'responsavel_vinculo.required_if' => 'Informe o vínculo com o aluno.',
            'aluno_nome.required' => 'Informe o nome do aluno.',
        ]);

        // Determina nome e contato do interessado (pessoa que preeche o form)
        $nomeInteressado = $validated['tipo_preenchimento'] === 'responsavel'
            ? $validated['responsavel_nome']
            : $validated['aluno_nome'];

        // Cria ou localiza a pessoa pelo e-mail
        $pessoa = Pessoa::firstOrCreate(
            ['email' => $validated['responsavel_email']],
            [
                'nome' => $nomeInteressado,
                'cpf' => $validated['responsavel_cpf'] ?? null,
                'telefone' => $validated['responsavel_telefone'],
            ]
        );

        // Status padrão "Novo"
        $statusNovo = StatusInteressado::where('nome', 'Novo')->first();

        // Origem: site público (cria se não existir)
        $origemSite = OrigemInteressado::firstOrCreate(
            ['nome' => 'Site'],
        );

        // Origem escolhida pelo usuário (se informada)
        $origemId = $validated['como_conheceu'] ?? $origemSite->id;

        // Cria ou atualiza o interessado
        $interessado = Interessado::updateOrCreate(
            ['pessoa_id' => $pessoa->id],
            [
                'status_interessado_id' => $statusNovo?->id,
                'origem_interessado_id' => $origemId,
                'observacoes' => $this->montarObservacoes($validated),
            ]
        );

        // Vincula dependente (o aluno)
        InteressadoDependente::create([
            'interessado_id' => $interessado->id,
            'nome_crianca' => $validated['aluno_nome'],
            'data_nascimento' => $validated['aluno_data_nascimento'] ?? null,
            'serie_id' => optional(Turma::find($validated['turma_id'] ?? null))->serie_id,
        ]);

        return redirect()->route('captacao.interessado.sucesso');
    }

    public function sucesso(): View
    {
        return view('captacao.sucesso');
    }

    /**
     * Monta texto de observações consolidando os dados do formulário.
     *
     * @param  array<string, mixed>  $data
     */
    private function montarObservacoes(array $data): string
    {
        $obs = [];

        if ($data['tipo_preenchimento'] === 'responsavel') {
            $obs[] = 'Vínculo com o aluno: '.($data['responsavel_vinculo'] ?? '-');
        }

        if (! empty($data['unidade_id'])) {
            $unidade = Unidade::find($data['unidade_id']);
            $obs[] = 'Unidade de interesse: '.($unidade?->nome ?? '-');
        }

        if (! empty($data['turma_id'])) {
            $turma = Turma::with('serie')->find($data['turma_id']);
            $obs[] = 'Turma de interesse: '.($turma?->nome ?? '-').' – '.($turma?->serie?->nome ?? '-');
        }

        if (! empty($data['turno_preferencia'])) {
            $obs[] = 'Turno de preferência: '.$data['turno_preferencia'];
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

        // Se as chaves não estiverem configuradas, pula a verificação (ambiente de teste/local)
        if (empty($siteKey) || empty($secret)) {
            \Log::info('reCAPTCHA ignorado: Chaves não configuradas no .env');
            return;
        }

        $token = $request->input('recaptcha_token');

        if (empty($token)) {
            // Se as chaves NÃO estão configuradas, apenas logamos e deixamos passar.
            // Se as chaves ESTÃO configuradas e o token faltou, aí sim é um erro.
            if (!empty($siteKey) && !empty($secret)) {
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
            \Log::error('Erro ao conectar com API do reCAPTCHA: ' . $e->getMessage());
            // Em caso de erro de conexão com o Google, deixamos passar para não travar o site
        }
    }
}
