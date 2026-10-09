<?php

namespace App\Livewire\MatriculaOnline;

use App\Enums\StatusTurma;
use App\Models\Curso;
use App\Models\Serie;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Unidade;
use App\Rules\RecaptchaV3;
use App\Services\MatriculaOnlineService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class MatriculaOnlineWizard extends Component
{
    use WithFileUploads;

    public int $passoAtual = 1;

    // Passo 1: Unidade, Curso e Turma
    public ?int $unidade_id = null;

    public ?int $curso_id = null;

    public ?int $serie_id = null;

    public ?int $turma_id = null;

    // Passo 2: Aluno
    public string $aluno_nome = '';

    public string $aluno_cpf = '';

    public string $aluno_data_nascimento = '';

    public string $aluno_sexo = 'masculino';

    public string $aluno_cor_raca = 'Parda';

    public bool $aluno_necessidades_especiais = false;

    // Passo 3: Responsável
    public string $responsavel_nome = '';

    public string $responsavel_cpf = '';

    public string $responsavel_telefone = '';

    public string $responsavel_email = '';

    public ?int $responsavel_tipo_vinculo_id = null;

    // Endereço
    public string $cep = '';

    public string $logradouro = '';

    public string $numero = '';

    public string $complemento = '';

    public string $bairro = '';

    public string $cidade_nome = '';

    public string $estado_sigla = '';

    // Passo 4: Documentos Anexados
    public $documento_aluno;

    public $documento_responsavel;

    public $comprovante_residencia;

    public $historico_anterior;

    // Passo 5: Contrato e Aceites
    public bool $aceite_contrato = false;

    public bool $aceite_lgpd = false;

    public bool $aceite_regimento = false;

    public bool $processando = false;

    public ?string $mensagemErro = null;

    /** Token do reCAPTCHA v3, preenchido no navegador imediatamente antes de finalizar. */
    public string $recaptcha_token = '';

    public function mount(): void
    {
        $primeiraUnidade = Unidade::where('flag_ativo', true)->first() ?? Unidade::first();
        if ($primeiraUnidade) {
            $this->unidade_id = $primeiraUnidade->id;
        }

        $primeiroVinculo = TipoVinculo::whereIn('nome', ['Pai', 'Mãe', 'Responsável Legal'])->first();
        if ($primeiroVinculo) {
            $this->responsavel_tipo_vinculo_id = $primeiroVinculo->id;
        }
    }

    public function getUnidadesProperty(): Collection
    {
        return Unidade::where('flag_ativo', true)->orderBy('nome')->get();
    }

    public function getCursosProperty(): Collection
    {
        if (! $this->unidade_id) {
            return collect();
        }

        return Curso::where('unidade_id', $this->unidade_id)->orderBy('nome_externo')->get();
    }

    public function getSeriesProperty(): Collection
    {
        if (! $this->curso_id) {
            return collect();
        }

        return Serie::where('curso_id', $this->curso_id)->orderBy('nome')->get();
    }

    public function getTurmasProperty(): Collection
    {
        if (! $this->serie_id) {
            return collect();
        }

        return Turma::where('serie_id', $this->serie_id)
            ->abertasParaMatricula()
            ->with(['turno', 'matriculas'])
            ->get();
    }

    public function getTurmaSelecionadaProperty(): ?Turma
    {
        if (! $this->turma_id) {
            return null;
        }

        return Turma::with(['serie.curso.unidade', 'turno'])->find($this->turma_id);
    }

    public function getTiposVinculoProperty(): Collection
    {
        return TipoVinculo::orderBy('nome')->get();
    }

    public function updatedUnidadeId(): void
    {
        $this->curso_id = null;
        $this->serie_id = null;
        $this->turma_id = null;
    }

    public function updatedCursoId(): void
    {
        $this->serie_id = null;
        $this->turma_id = null;
    }

    public function updatedSerieId(): void
    {
        $this->turma_id = null;
    }

    public function buscarCep(): void
    {
        $cepLimpo = preg_replace('/\D/', '', $this->cep);

        if (strlen($cepLimpo) !== 8) {
            return;
        }

        try {
            $response = Http::timeout(4)->get("https://viacep.com.br/ws/{$cepLimpo}/json/");

            if ($response->successful() && ! isset($response->json()['erro'])) {
                $dados = $response->json();
                $this->logradouro = $dados['logradouro'] ?? $this->logradouro;
                $this->bairro = $dados['bairro'] ?? $this->bairro;
                $this->cidade_nome = $dados['localidade'] ?? $this->cidade_nome;
                $this->estado_sigla = $dados['uf'] ?? $this->estado_sigla;
            }
        } catch (\Throwable) {
            // Silencioso em caso de falha de conexão com ViaCEP
        }
    }

    public function avancarPasso(): void
    {
        $this->mensagemErro = null;

        if ($this->passoAtual === 1) {
            $this->validate([
                'unidade_id' => 'required|exists:unidade,id',
                'curso_id' => 'required|exists:curso,id',
                'serie_id' => 'required|exists:serie,id',
                'turma_id' => ['required', Rule::exists('turma', 'id')->whereIn('status', StatusTurma::valoresAbertosParaMatricula())],
            ], [
                'unidade_id.required' => 'Selecione a Unidade Escolar.',
                'curso_id.required' => 'Selecione o Curso pretendido.',
                'serie_id.required' => 'Selecione a Série / Ano.',
                'turma_id.required' => 'Selecione uma Turma disponível com vagas.',
            ]);

            // Checagem de vagas em tempo real
            $turma = Turma::find($this->turma_id);
            if ($turma && $turma->vagas_maximas && $turma->matriculas()->count() >= $turma->vagas_maximas) {
                $this->addError('turma_id', 'A turma selecionada está esgotada. Por favor, escolha outra turma ou turno.');

                return;
            }

            $this->passoAtual = 2;

            return;
        }

        if ($this->passoAtual === 2) {
            $this->validate([
                'aluno_nome' => 'required|string|min:4|max:255',
                'aluno_cpf' => 'nullable|string|max:18',
                'aluno_data_nascimento' => 'required|date|before:today',
                'aluno_sexo' => 'required|in:masculino,feminino',
                'aluno_cor_raca' => 'required|string',
            ], [
                'aluno_nome.required' => 'O nome completo do estudante é obrigatório.',
                'aluno_data_nascimento.required' => 'A data de nascimento é obrigatória.',
                'aluno_data_nascimento.before' => 'A data de nascimento deve ser anterior à data de hoje.',
            ]);

            $this->passoAtual = 3;

            return;
        }

        if ($this->passoAtual === 3) {
            $this->validate([
                'responsavel_nome' => 'required|string|min:4|max:255',
                'responsavel_cpf' => 'required|string|min:11|max:18',
                'responsavel_telefone' => 'required|string|min:9|max:30',
                'responsavel_email' => 'required|email|max:255',
                'responsavel_tipo_vinculo_id' => 'required|exists:tipo_vinculos,id',
                'cep' => 'required|string|min:8|max:10',
                'logradouro' => 'required|string|max:255',
                'numero' => 'required|string|max:30',
                'bairro' => 'required|string|max:100',
                'cidade_nome' => 'required|string|max:100',
                'estado_sigla' => 'required|string|max:2',
            ], [
                'responsavel_nome.required' => 'O nome do responsável é obrigatório.',
                'responsavel_cpf.required' => 'O CPF do responsável é obrigatório para o contrato.',
                'responsavel_telefone.required' => 'O telefone/WhatsApp de contato é obrigatório.',
                'responsavel_email.required' => 'O e-mail é obrigatório para envio de comprovantes e acesso ao Portal.',
                'cep.required' => 'O CEP residencial é obrigatório.',
                'logradouro.required' => 'O endereço residencial é obrigatório.',
                'numero.required' => 'O número do endereço é obrigatório.',
            ]);

            $this->passoAtual = 4;

            return;
        }

        if ($this->passoAtual === 4) {
            $this->validate([
                'documento_aluno' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'documento_responsavel' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'comprovante_residencia' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'historico_anterior' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ], [
                'documento_aluno.mimes' => 'O documento do aluno deve ser um arquivo PDF ou imagem (JPG, PNG).',
                'documento_aluno.max' => 'O arquivo não pode ter mais de 5MB.',
            ]);

            $this->passoAtual = 5;

            return;
        }
    }

    public function voltarPasso(): void
    {
        if ($this->passoAtual > 1) {
            $this->passoAtual--;
            $this->mensagemErro = null;
        }
    }

    public function finalizarMatricula(MatriculaOnlineService $service): mixed
    {
        $this->mensagemErro = null;

        $this->validate([
            'aceite_contrato' => 'accepted',
            'aceite_lgpd' => 'accepted',
            'aceite_regimento' => 'accepted',
        ], [
            'aceite_contrato.accepted' => 'É obrigatório declarar ciência e aceitar os termos do Contrato de Prestação de Serviços Educacionais.',
            'aceite_lgpd.accepted' => 'É obrigatório autorizar o tratamento de dados pessoais conforme a LGPD.',
            'aceite_regimento.accepted' => 'É obrigatório declarar ciência e concordância com as normas regimentais da instituição.',
        ]);

        // Formulário público que grava pessoas, contrato e conta de acesso: limita tentativas por IP e exige reCAPTCHA.
        $limiteChave = 'matricula-online:'.request()->ip();
        $maxTentativas = (int) config('seguranca.matricula_online.max_tentativas', 6);

        if (RateLimiter::tooManyAttempts($limiteChave, $maxTentativas)) {
            $this->mensagemErro = 'Recebemos muitas tentativas deste dispositivo. Aguarde alguns minutos e tente novamente, ou fale com a secretaria da escola.';

            return null;
        }

        RateLimiter::hit($limiteChave, ((int) config('seguranca.matricula_online.janela_minutos', 60)) * 60);

        $this->validate([
            'recaptcha_token' => [new RecaptchaV3(request()->ip())],
        ]);

        $this->recaptcha_token = '';
        $this->processando = true;

        try {
            $dados = [
                'turma_id' => $this->turma_id,
                'aluno' => [
                    'nome' => $this->aluno_nome,
                    'cpf' => $this->aluno_cpf,
                    'data_nascimento' => $this->aluno_data_nascimento,
                    'sexo' => $this->aluno_sexo,
                    'cor_raca' => $this->aluno_cor_raca,
                    'necessidades_especiais' => $this->aluno_necessidades_especiais,
                ],
                'responsavel' => [
                    'nome' => $this->responsavel_nome,
                    'cpf' => $this->responsavel_cpf,
                    'email' => $this->responsavel_email,
                    'telefone' => $this->responsavel_telefone,
                    'tipo_vinculo_id' => $this->responsavel_tipo_vinculo_id,
                    'cep' => $this->cep,
                    'logradouro' => $this->logradouro,
                    'numero' => $this->numero,
                    'complemento' => $this->complemento,
                    'bairro' => $this->bairro,
                    'cidade_nome' => $this->cidade_nome,
                    'estado_sigla' => $this->estado_sigla,
                ],
            ];

            $arquivos = [
                'documento_aluno' => $this->documento_aluno,
                'documento_responsavel' => $this->documento_responsavel,
                'comprovante_residencia' => $this->comprovante_residencia,
                'historico_anterior' => $this->historico_anterior,
            ];

            $matricula = $service->processarMatricula($dados, $arquivos);

            // A tela de confirmação mostra dados do aluno e do responsável: abre na mesma sessão do navegador (o id fica
            // na sessão) ou pelo link assinado que expira, em vez de depender só do número da matrícula (sequencial,
            // portanto adivinhável).
            session(['matricula_online_id' => $matricula->id]);

            return redirect()->to(URL::temporarySignedRoute(
                'matricular.online.sucesso',
                now()->addHours((int) config('seguranca.matricula_online.link_sucesso_horas', 2)),
                ['matricula' => $matricula->id],
            ));
        } catch (\DomainException $e) {
            $this->processando = false;
            $this->mensagemErro = $e->getMessage();
        } catch (\Throwable $e) {
            // Nunca devolve a mensagem da exceção ao público: ela pode trazer SQL, caminhos e dados de outras pessoas.
            report($e);
            $this->processando = false;
            $this->mensagemErro = 'Ocorreu um erro ao processar sua matrícula. Revise os dados e tente novamente ou entre em contato com nossa equipe.';
        }

        return null;
    }

    public function render()
    {
        return view('livewire.matricula-online.matricula-online-wizard')
            ->layout('layouts.public');
    }
}
