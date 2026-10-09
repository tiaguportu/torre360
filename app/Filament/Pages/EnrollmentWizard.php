<?php

namespace App\Filament\Pages;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use App\Enums\SituacaoDocumento;
use App\Enums\SituacaoMatricula;
use App\Exceptions\TurmaIndisponivelException;
use App\Models\AlunoResponsavel;
use App\Models\Cidade;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\DocumentoInserido;
use App\Models\Endereco;
use App\Models\Interessado;
use App\Models\Matricula;
use App\Models\Pais;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\PropostaComercial;
use App\Models\ResponsavelFinanceiro;
use App\Models\TipoDocumento;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Models\VideoTutorial;
use App\Notifications\WelcomeUserMail;
use App\Services\InteressadoMatriculaService;
use App\Services\TurmaVagasService;
use App\Support\TiposArquivo;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class EnrollmentWizard extends Page implements HasForms, HasShieldPermissions
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?string $navigationLabel = 'Nova Matrícula (Wizard)';

    protected static ?string $title = 'Assistente de Matrícula';

    public static function canAccess(): bool
    {
        return auth()->user()->can('View:EnrollmentWizard');
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
        ];
    }

    protected string $view = 'filament.pages.enrollment-wizard';

    public ?array $data = [];

    /**
     * Lead do CRM que originou a matrícula (rota `?interessado={id}`). Travado
     * para não ser alterado pelo cliente entre as requisições do Livewire.
     */
    #[Locked]
    public ?int $interessadoId = null;

    public function mount(?int $interessado = null): void
    {
        $dados = [
            'data_ativacao' => now()->toDateString(),
            'situacao' => SituacaoMatricula::ATIVA->value,
            'periodo_letivo_id' => PeriodoLetivo::latest('id')->value('id'),
        ];

        $interessadoIdAlvo = $interessado ?: request()->integer('interessado');
        $interessado = $interessadoIdAlvo ? Interessado::with(['pessoa', 'dependentes.serie.curso'])->find($interessadoIdAlvo) : null;

        if ($interessado) {
            $this->interessadoId = $interessado->id;
            $dados = array_merge($dados, InteressadoMatriculaService::dadosParaWizard($interessado));

            Notification::make()
                ->title('Dados do lead carregados')
                ->body("Formulário pré-preenchido com os dados de {$interessado->pessoa?->nome}"
                    .(filled($interessado->dados_pre_matricula) ? ' e com a pré-matrícula online preenchida pela família (responsáveis, alunos e endereço)' : '')
                    .'. Revise e complete as informações.')
                ->info()
                ->send();
        }

        $propostaId = request()->integer('proposta_id');
        if ($propostaId && ! $interessado) {
            $proposta = PropostaComercial::find($propostaId);
            if ($proposta) {
                if ($proposta->interessado_id) {
                    $this->interessadoId = $proposta->interessado_id;
                }
                $dados['unidade_id'] = $proposta->unidade_id;
                $dados['curso_id'] = $proposta->curso_id;
                if ($proposta->turma_id) {
                    $dados['turma_id'] = $proposta->turma_id;
                }
                $dados['responsaveis'] = [
                    [
                        'nome' => $proposta->responsavel_nome,
                        'telefone' => $proposta->responsavel_telefone,
                        'email' => $proposta->responsavel_email,
                        'responsavel_financeiro' => true,
                        'tipo_vinculo' => 'Responsável Legal',
                    ],
                ];
                if ($proposta->aluno_nome) {
                    $dados['alunos'] = [
                        [
                            'nome' => $proposta->aluno_nome,
                        ],
                    ];
                }

                Notification::make()
                    ->title("Proposta {$proposta->codigo} Carregada")
                    ->body('Formulário pré-preenchido com os dados da Proposta Comercial aprovada (Mensalidade Líquida: R$ '.number_format((float) $proposta->valor_liquido_mensal, 2, ',', '.').').')
                    ->success()
                    ->send();
            }
        }

        $this->form->fill($dados);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Assistente de Matrícula')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'enrollment-wizard-matricula')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>O <strong>Assistente de Matrícula</strong> guia você em 4 etapas para cadastrar um ou mais alunos de uma mesma família, criando automaticamente as matrículas, contratos e vínculos de responsabilidade.</p>';

        $html .= '<h3>Etapas</h3><ol>';
        $html .= '<li><strong>Dados do(s) Aluno(s):</strong> Adicione quantos filhos forem necessários. Digite o CPF para buscar automaticamente um cadastro já existente. Preencha nome, data de nascimento, endereço e, se desejar, crie um acesso de portal para o aluno.</li>';
        $html .= '<li><strong>Pais / Responsáveis:</strong> Cadastre os responsáveis da família. O CPF também busca cadastros existentes. Defina o vínculo (Pai, Mãe, Avó, etc.) e se é responsável financeiro. Os responsáveis serão vinculados a <em>todos</em> os alunos adicionados na etapa anterior.</li>';
        $html .= '<li><strong>Plano e Matrícula:</strong> Selecione a unidade, o período letivo, o curso e a turma. As turmas são filtradas automaticamente pela unidade e pelo curso escolhidos.</li>';
        $html .= '<li><strong>Documentos da Matrícula:</strong> Confira os documentos enviados pela família no Portal de Admissão ou anexe novos documentos recebidos presencialmente.</li>';
        $html .= '</ol>';

        $html .= '<h3>Regra de Liberação do Contrato Escolar</h3><ul>';
        $html .= '<li>A matrícula pode ser criada normalmente a qualquer momento.</li>';
        $html .= '<li><strong>Emissão de Contrato:</strong> O Contrato Escolar só será gerado e a matrícula <strong>Ativada</strong> se todos os documentos classificados como <em>Obrigatórios para Contrato</em> estiverem presentes e válidos.</li>';
        $html .= '<li>Se faltar algum documento obrigatório de contrato, a matrícula será salva na situação <strong>Pendente</strong>, sem emissão de contrato, até a regularização.</li>';
        $html .= '<li>Documentos de <em>Histórico do Aluno</em> não bloqueiam a emissão do contrato (a matrícula tem contrato gerado e fica Ativa com pendência de histórico).</li>';
        $html .= '</ul>';

        return $html;
    }

    public function form(Schema $schema): Schema
    {
        /**
         * Gera os campos de identificação de uma Pessoa (aluno ou responsável).
         * O $statePath é o prefixo relativo dentro do item do Repeater.
         */
        $getPessoaFields = function () {
            return [
                FileUpload::make('foto')
                    ->image()
                    ->acceptedFileTypes(TiposArquivo::imagens())
                    ->imageEditor()
                    ->imageEditorAspectRatios(['3:4'])
                    ->directory('pessoas_fotos')
                    ->columnSpanFull(),

                TextInput::make('cpf')
                    ->label('CPF')
                    ->maxLength(14)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? preg_replace('/\D/', '', $state) : null)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($set, $state) {
                        if (empty($state)) {
                            return;
                        }

                        $cleanState = preg_replace('/\D/', '', $state);

                        $pessoa = Pessoa::with('enderecos')
                            ->where('cpf', $cleanState)
                            ->orWhereRaw("REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?", [$cleanState])
                            ->first();

                        if ($pessoa) {
                            $endereco = $pessoa->enderecos->first();

                            $set('nome', $pessoa->nome);
                            // Pessoa::data_nascimento não tem cast de data: chega como string do banco.
                            $set('data_nascimento', $pessoa->data_nascimento ? Carbon::parse($pessoa->data_nascimento)->toDateString() : null);
                            $set('email', $pessoa->email);
                            $set('telefone', $pessoa->telefone);
                            $set('nacionalidade_id', (string) $pessoa->nacionalidade_id);
                            $set('naturalidade_id', (string) $pessoa->naturalidade_id);
                            $set('sexo', $pessoa->sexo?->value);
                            $set('cor_raca', $pessoa->cor_raca instanceof CorRaca ? $pessoa->cor_raca->value : $pessoa->cor_raca);
                            $set('pessoa_id_existente', $pessoa->id);

                            if ($endereco) {
                                $set('cidade_id', (string) $endereco->cidade_id);
                                $set('cep', $endereco->cep);
                                $set('logradouro', $endereco->logradouro);
                                $set('numero', $endereco->numero);
                                $set('bairro', $endereco->bairro);
                                $set('complemento', $endereco->complemento);
                            }

                            Notification::make()
                                ->title('Cadastro encontrado')
                                ->body("Pessoa identificada: {$pessoa->nome}")
                                ->success()
                                ->send();
                        } else {
                            $set('pessoa_id_existente', null);

                            Notification::make()
                                ->title('Aviso')
                                ->body('Nenhum registro encontrado para este CPF. Preencha os dados abaixo.')
                                ->warning()
                                ->send();
                        }
                    }),

                // Campo oculto para guardar o ID de pessoa já existente
                TextInput::make('pessoa_id_existente')
                    ->hidden()
                    ->dehydrated()
                    ->dehydratedWhenHidden(),

                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('data_nascimento')
                    ->label('Data de Nascimento')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                TextInput::make('email')
                    ->email()
                    ->maxLength(255)
                    ->live()
                    ->afterStateUpdated(function ($set, $get, $state) {
                        if (empty($state) || ! $get('criar_usuario')) {
                            return;
                        }

                        if (User::where('email', $state)->exists()) {
                            Notification::make()
                                ->title('Atenção')
                                ->body('Este e-mail já está em uso. A pessoa será vinculada ao usuário existente.')
                                ->warning()
                                ->send();
                        }
                    }),

                TextInput::make('telefone')
                    ->tel()
                    ->maxLength(20),

                Select::make('nacionalidade_id')
                    ->label('Nacionalidade')
                    ->options(Pais::pluck('nome', 'id'))
                    ->default(fn () => Pais::where('nome', 'Brasil')->value('id'))
                    ->searchable()
                    ->live(),

                Select::make('naturalidade_id')
                    ->label('Naturalidade')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Cidade::query()
                        ->with('estado')
                        ->where('nome', 'like', "%{$search}%")
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn ($cidade) => [$cidade->id => "{$cidade->nome} - ".($cidade->estado?->sigla ?? '')])
                        ->toArray())
                    ->getOptionLabelUsing(fn ($value): ?string => ($c = Cidade::with('estado')->find($value)) ? "{$c->nome} - ".($c->estado?->sigla ?? '') : null)
                    ->visible(fn ($get) => $get('nacionalidade_id') == Pais::where('nome', 'Brasil')->value('id')),

                Select::make('sexo')
                    ->label('Sexo')
                    ->options(Sexo::class)
                    ->searchable(),

                Select::make('cor_raca')
                    ->label('Cor/Raça')
                    ->options(CorRaca::class)
                    ->searchable(),

                Checkbox::make('criar_usuario')
                    ->label('Criar conta de acesso para esta pessoa?')
                    ->helperText('Será enviado um e-mail com a senha para o endereço informado acima.')
                    ->live()
                    ->afterStateUpdated(function ($get, $state) {
                        if (! $state) {
                            return;
                        }

                        $email = $get('email');
                        if (empty($email)) {
                            return;
                        }

                        if (User::where('email', $email)->exists()) {
                            Notification::make()
                                ->title('Atenção')
                                ->body('Este e-mail já está em uso. A pessoa será vinculada ao usuário existente.')
                                ->warning()
                                ->send();
                        }
                    })
                    ->default(false)
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => ! empty($get('email'))),
            ];
        };

        $enderecoFields = [
            TextInput::make('cep')
                ->label('CEP')
                ->mask('99999-999')
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, $set) {
                    if (empty($state)) {
                        return;
                    }

                    $cep = preg_replace('/\D/', '', $state);
                    if (strlen($cep) !== 8) {
                        return;
                    }

                    try {
                        $response = Http::get("https://viacep.com.br/ws/{$cep}/json/")->json();

                        if (isset($response['erro'])) {
                            return;
                        }

                        $set('logradouro', $response['logradouro'] ?? '');
                        $set('bairro', $response['bairro'] ?? '');
                        $set('complemento', $response['complemento'] ?? '');

                        if (isset($response['ibge'])) {
                            $cidade = Cidade::where('codigo_ibge', $response['ibge'])->first();
                            if ($cidade) {
                                $set('cidade_id', (string) $cidade->id);
                            }
                        }
                    } catch (\Exception $e) {
                        // Silently fail if API is down
                    }
                }),

            Select::make('cidade_id')
                ->label('Cidade')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Cidade::query()
                    ->with('estado')
                    ->where('nome', 'like', "%{$search}%")
                    ->limit(20)
                    ->get()
                    ->mapWithKeys(fn ($cidade) => [$cidade->id => "{$cidade->nome} - ".($cidade->estado?->sigla ?? '')])
                    ->toArray())
                ->getOptionLabelUsing(fn ($value): ?string => ($c = Cidade::with('estado')->find($value)) ? "{$c->nome} - ".($c->estado?->sigla ?? '') : null),

            TextInput::make('logradouro')->label('Logradouro'),
            TextInput::make('numero')->label('Número'),
            TextInput::make('complemento')->label('Complemento'),
            TextInput::make('bairro')->label('Bairro'),
        ];

        return $schema
            ->components([
                Wizard::make([

                    // ═══════════════════════════════════════════════════
                    // STEP 1 — Alunos
                    // ═══════════════════════════════════════════════════
                    Step::make('Dados do(s) Aluno(s)')
                        ->description('Identificação básica do(s) estudante(s)')
                        ->icon('heroicon-m-user')
                        ->components([
                            Repeater::make('alunos')
                                ->label('Alunos')
                                ->addActionLabel('Adicionar Aluno')
                                ->minItems(1)
                                ->schema([
                                    Section::make('Identificação do Aluno')
                                        ->columns(2)
                                        ->schema($getPessoaFields()),

                                    Section::make('Endereço do Aluno')
                                        ->columns(2)
                                        ->schema($enderecoFields),
                                ])
                                ->collapsible()
                                ->cloneable()
                                ->itemLabel(fn (array $state): ?string => $state['nome'] ?? 'Novo Aluno'),
                        ]),

                    // ═══════════════════════════════════════════════════
                    // STEP 2 — Responsáveis
                    // ═══════════════════════════════════════════════════
                    Step::make('Pais / Responsáveis')
                        ->description('Vínculos familiares e financeiros')
                        ->icon('heroicon-m-users')
                        ->components([
                            Repeater::make('responsaveis')
                                ->label('Responsáveis')
                                ->addActionLabel('Adicionar Responsável')
                                ->minItems(1)
                                ->schema([
                                    Grid::make(3)
                                        ->schema([
                                            Select::make('tipo_vinculo_id')
                                                ->label('Vínculo')
                                                ->options(TipoVinculo::pluck('nome', 'id'))
                                                ->required(),

                                            Checkbox::make('is_financeiro')
                                                ->label('Responsável Financeiro?')
                                                ->live()
                                                ->default(true),

                                            TextInput::make('percentual')
                                                ->label('Percentual (%)')
                                                ->numeric()
                                                ->default(100)
                                                ->visible(fn ($get) => $get('is_financeiro'))
                                                ->required(fn ($get) => $get('is_financeiro')),
                                        ]),

                                    Section::make('Identificação do Responsável')
                                        ->columns(2)
                                        ->schema($getPessoaFields()),

                                    Section::make('Endereço do Responsável')
                                        ->columns(2)
                                        ->schema($enderecoFields),
                                ])
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['nome'] ?? 'Novo Responsável'),
                        ]),

                    // ═══════════════════════════════════════════════════
                    // STEP 3 — Plano e Matrícula (MELHORADO)
                    // ═══════════════════════════════════════════════════
                    Step::make('Plano e Matrícula')
                        ->description('Definição de curso, turma e configurações da matrícula')
                        ->icon('heroicon-m-academic-cap')
                        ->components([
                            Section::make('Escola e Turma')
                                ->columns(2)
                                ->schema([
                                    Select::make('unidade_id')
                                        ->label('Unidade / Escola')
                                        ->options(Unidade::pluck('nome', 'id'))
                                        ->searchable()
                                        ->required()
                                        ->live(),

                                    Select::make('periodo_letivo_id')
                                        ->label('Período Letivo')
                                        ->options(PeriodoLetivo::orderByDesc('id')->pluck('nome', 'id'))
                                        ->searchable()
                                        ->required()
                                        ->live(),

                                    Select::make('curso_id')
                                        ->label('Curso')
                                        ->options(fn (Get $get) => Curso::when(
                                            $get('unidade_id'),
                                            fn ($q, $unidade) => $q->where('unidade_id', $unidade)
                                        )->pluck('nome_interno', 'id'))
                                        ->live()
                                        ->searchable()
                                        ->required(),

                                    Select::make('turma_id')
                                        ->label('Turma')
                                        ->options(function (Get $get) {
                                            $cursoId = $get('curso_id');
                                            $periodoLetivoId = $get('periodo_letivo_id');

                                            if (! $cursoId) {
                                                return [];
                                            }

                                            // Toda turma tem período letivo; só as abertas para matrícula
                                            // (Planejada e Ativa) são oferecidas — Concluída e Cancelada não.
                                            return Turma::whereHas('serie', fn ($q) => $q->where('curso_id', $cursoId))
                                                ->abertasParaMatricula()
                                                ->when($periodoLetivoId, fn ($q) => $q->where('periodo_letivo_id', $periodoLetivoId))
                                                ->orderBy('nome')
                                                ->get()
                                                ->mapWithKeys(function (Turma $turma) {
                                                    $matriculasAtivas = $turma->matriculas()->count();
                                                    $vagas = $turma->vagas_maximas;
                                                    $vagasLabel = $vagas
                                                        ? " ({$matriculasAtivas}/{$vagas} vagas)"
                                                        : " ({$matriculasAtivas} matriculados)";

                                                    $icone = ($vagas && $matriculasAtivas >= $vagas) ? ' 🔴 LOTADA' : '';

                                                    return [$turma->id => $turma->nome.$vagasLabel.$icone];
                                                })
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->required()
                                        ->helperText('As turmas mostram (matriculados/vagas). Turmas marcadas com 🔴 estão lotadas.'),
                                ]),

                            Section::make('Configurações da Matrícula')
                                ->columns(2)
                                ->schema([
                                    Select::make('situacao')
                                        ->label('Situação Inicial')
                                        ->options([
                                            SituacaoMatricula::ATIVA->value => SituacaoMatricula::ATIVA->getLabel(),
                                            SituacaoMatricula::PENDENTE->value => SituacaoMatricula::PENDENTE->getLabel(),
                                            SituacaoMatricula::RESERVA->value => SituacaoMatricula::RESERVA->getLabel(),
                                        ])
                                        ->default(SituacaoMatricula::ATIVA->value)
                                        ->required()
                                        ->native(false)
                                        ->helperText('Use "Pendente" quando a documentação ainda não foi entregue.'),

                                    DatePicker::make('data_ativacao')
                                        ->label('Data de Ativação')
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->default(now()->toDateString()),
                                ]),
                        ]),

                    // ═══════════════════════════════════════════════════
                    // STEP 4 — Documentos da Matrícula & Liberação de Contrato
                    // ═══════════════════════════════════════════════════
                    Step::make('Documentos da Matrícula')
                        ->description('Conferência de documentos e liberação do contrato escolar')
                        ->icon('heroicon-m-document-text')
                        ->components([
                            Placeholder::make('info_regra_contrato')
                                ->label('')
                                ->content(new HtmlString('
                                    <div class="p-4 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-950 dark:text-indigo-200 space-y-1 mb-3">
                                        <span class="font-bold text-indigo-900 dark:text-indigo-100 block">Regra de Liberação do Contrato Escolar:</span>
                                        <p class="leading-relaxed">
                                            A matrícula pode ser cadastrada a qualquer momento. No entanto, o <strong>Contrato Escolar só será emitido e a matrícula Ativada</strong> se todos os documentos classificados como <strong>Obrigatórios para Contrato</strong> estiverem entregues. Caso falte algum documento obrigatório de contrato, a matrícula será salva na situação <strong>Pendente</strong>.
                                        </p>
                                    </div>
                                ')),

                            ViewField::make('docs_lead')
                                ->view('filament.components.wizard-docs-lead')
                                ->viewData(fn (Get $get) => [
                                    'interessado' => $this->interessadoId ? Interessado::find($this->interessadoId) : null,
                                    'cursoId' => $get('curso_id') ?: (Turma::find($get('turma_id'))?->serie?->curso_id),
                                ]),

                            Repeater::make('documentos_anexados')
                                ->label('Anexar Novos Documentos (Entregues Presencialmente)')
                                ->schema([
                                    Select::make('tipo_documento_id')
                                        ->label('Tipo de Documento')
                                        ->options(function (Get $get) {
                                            $cursoId = $get('../../curso_id');
                                            $turmaId = $get('../../turma_id');
                                            if (! $cursoId && $turmaId) {
                                                $cursoId = Turma::find($turmaId)?->serie?->curso_id;
                                            }

                                            return TipoDocumento::visivelPortalFamilia()
                                                ->when(
                                                    $cursoId,
                                                    fn ($q) => $q->paraCursos($cursoId),
                                                    fn ($q) => $q->whereDoesntHave('cursos')
                                                )
                                                ->pluck('nome', 'id');
                                        })
                                        ->required()
                                        ->searchable(),

                                    FileUpload::make('arquivo')
                                        ->label('Arquivo (PDF ou Imagem)')
                                        ->directory('documentos_matricula')
                                        ->acceptedFileTypes(TiposArquivo::documentos())
                                        ->maxSize(10240)
                                        ->required(),
                                ])
                                ->columns(2)
                                ->addActionLabel('+ Anexar Documento Presencial')
                                ->collapsible(),
                        ]),
                ])
                    ->submitAction(
                        Action::make('save')
                            ->label('Finalizar Matrícula')
                            ->color('success')
                            ->icon('heroicon-m-check-circle')
                            ->action('save')
                    ),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $raw = $this->form->getState();

        try {
            DB::beginTransaction();

            // ── Validação de status e de vagas ──────────────────────────
            // Trava a turma na transação: dois atendentes matriculando na última vaga passam um de cada vez.
            try {
                app(TurmaVagasService::class)->garantirVaga((int) $raw['turma_id'], count($raw['alunos']));
            } catch (TurmaIndisponivelException $e) {
                DB::rollBack();

                Notification::make()
                    ->title($e->turmaSemVaga() ? 'Turma sem vagas suficientes' : 'Turma indisponível')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                return;
            }

            $turma = Turma::with('serie')->find($raw['turma_id']);

            // ── Checagem de Documentos Obrigatórios para Emissão do Contrato ──
            $cursoAlvoId = $raw['curso_id'] ?? $turma?->serie?->curso_id;
            $tiposObrigatoriosContrato = TipoDocumento::obrigatoriosParaContrato()
                ->when(
                    $cursoAlvoId,
                    fn ($q) => $q->paraCursos($cursoAlvoId),
                    fn ($q) => $q->whereDoesntHave('cursos')
                )
                ->get();

            // Mapeamento dos tipos já entregues (do lead no CRM ou anexados no formulário)
            $tiposEntreguesIds = collect();
            if ($this->interessadoId) {
                $docsLead = DocumentoInserido::where('interessado_id', $this->interessadoId)
                    ->whereIn('status', [SituacaoDocumento::EM_ANALISE, SituacaoDocumento::VERIFICADO])
                    ->pluck('tipo_documento_id');
                $tiposEntreguesIds = $tiposEntreguesIds->merge($docsLead);
            }
            if (! empty($raw['documentos_anexados'])) {
                $docsNovos = collect($raw['documentos_anexados'])->pluck('tipo_documento_id')->filter();
                $tiposEntreguesIds = $tiposEntreguesIds->merge($docsNovos);
            }

            $todosDocsContratoOk = $tiposObrigatoriosContrato->every(fn ($tipo) => $tiposEntreguesIds->contains($tipo->id));

            // Regra Contratual: sem os documentos obrigatórios, a matrícula DEVE ser Pendente e sem contrato
            $situacaoFinal = $todosDocsContratoOk
                ? ($raw['situacao'] ?? SituacaoMatricula::ATIVA->value)
                : SituacaoMatricula::PENDENTE->value;

            $dataAtivacaoFinal = ($situacaoFinal === SituacaoMatricula::ATIVA->value)
                ? ($raw['data_ativacao'] ?? now()->toDateString())
                : null;

            /** @var list<array{aluno: Pessoa, contrato: ?Contrato, matricula: Matricula}> $alunosPessoa */
            $alunosPessoa = [];

            foreach ($raw['alunos'] as $alunoData) {

                // ── Buscar ou criar Pessoa Aluno ────────────────────────
                $aluno = $this->buscarOuCriarPessoa($alunoData);

                // ── Endereço do Aluno (apenas se não existia antes) ─────
                if (! ($alunoData['pessoa_id_existente'] ?? null) && (! empty($alunoData['logradouro']) || ! empty($alunoData['cidade_id']))) {
                    $endereco = Endereco::create([
                        'cidade_id' => $alunoData['cidade_id'] ?? null,
                        'logradouro' => $alunoData['logradouro'] ?? null,
                        'numero' => $alunoData['numero'] ?? null,
                        'complemento' => $alunoData['complemento'] ?? null,
                        'bairro' => $alunoData['bairro'] ?? null,
                        'cep' => $alunoData['cep'] ?? null,
                    ]);
                    $aluno->enderecos()->attach($endereco->id);
                }

                // ── Criar usuário para o aluno, se solicitado ───────────
                if (! empty($alunoData['criar_usuario']) && ! empty($alunoData['email'])) {
                    $this->criarUsuarioParaPessoa($aluno, $alunoData['email'], 'aluno');
                }

                // ── Criar Matrícula ─────────────────────────────────────
                $matricula = Matricula::create([
                    'pessoa_id' => $aluno->id,
                    'turma_id' => $raw['turma_id'],
                    'situacao' => $situacaoFinal,
                    'data_ativacao' => $dataAtivacaoFinal,
                ]);

                // ── Salvar novos documentos anexados no assistente ──────
                if (! empty($raw['documentos_anexados'])) {
                    foreach ($raw['documentos_anexados'] as $docItem) {
                        if (! empty($docItem['arquivo']) && ! empty($docItem['tipo_documento_id'])) {
                            DocumentoInserido::create([
                                'matricula_id' => $matricula->id,
                                'tipo_documento_id' => (int) $docItem['tipo_documento_id'],
                                'arquivo_path' => $docItem['arquivo'],
                                'nome_arquivo_original' => basename((string) $docItem['arquivo']),
                                'status' => SituacaoDocumento::EM_ANALISE,
                            ]);
                        }
                    }
                }

                // ── Criar Contrato APENAS se os documentos obrigatórios estão presentes ──
                $contrato = null;
                if ($todosDocsContratoOk) {
                    $contrato = Contrato::create([
                        'matricula_id' => $matricula->id,
                        'valor_total' => 0,
                        'data_aceite' => now(),
                        'log_assinatura' => 'Gerado automaticamente pelo Assistente de Matrícula (Docs Obrigatórios Validados)',
                    ]);
                }

                // ── Integração CRM: marcar conversão ────────────────────
                $this->marcarConversaoCRM($aluno);

                $alunosPessoa[] = ['aluno' => $aluno, 'contrato' => $contrato, 'matricula' => $matricula];
            }

            // ── Responsáveis ────────────────────────────────────────────
            foreach ($raw['responsaveis'] as $respData) {
                $responsavelPessoa = $this->buscarOuCriarPessoa($respData);

                // Endereço do responsável (apenas se não existia antes)
                if (! ($respData['pessoa_id_existente'] ?? null) && (! empty($respData['logradouro']) || ! empty($respData['cidade_id']))) {
                    $enderecoResp = Endereco::create([
                        'cidade_id' => $respData['cidade_id'] ?? null,
                        'logradouro' => $respData['logradouro'] ?? null,
                        'numero' => $respData['numero'] ?? null,
                        'complemento' => $respData['complemento'] ?? null,
                        'bairro' => $respData['bairro'] ?? null,
                        'cep' => $respData['cep'] ?? null,
                    ]);
                    $responsavelPessoa->enderecos()->attach($enderecoResp->id);
                }

                // Criar usuário para o responsável, se solicitado
                if (! empty($respData['criar_usuario']) && ! empty($respData['email'])) {
                    $this->criarUsuarioParaPessoa($responsavelPessoa, $respData['email'], 'responsavel');
                }

                // Vincular responsável a todos os alunos
                foreach ($alunosPessoa as $entry) {
                    $alunoObj = $entry['aluno'];
                    $contratoObj = $entry['contrato'];

                    // Evitar duplicata no vínculo aluno-responsável
                    $jaVinculado = $alunoObj->responsaveis()
                        ->wherePivot('tipo_vinculo_id', $respData['tipo_vinculo_id'])
                        ->where('pessoa.id', $responsavelPessoa->id)
                        ->exists();

                    if (! $jaVinculado) {
                        AlunoResponsavel::create([
                            'aluno_id' => $alunoObj->id,
                            'responsavel_id' => $responsavelPessoa->id,
                            'tipo_vinculo_id' => $respData['tipo_vinculo_id'],
                        ]);
                    }

                    // Se o contrato foi emitido, vincula o responsável financeiro ao contrato
                    if ($contratoObj && ($respData['is_financeiro'] ?? false)) {
                        ResponsavelFinanceiro::create([
                            'pessoa_id' => $responsavelPessoa->id,
                            'contrato_id' => $contratoObj->id,
                            'percentual' => $respData['percentual'] ?? 100,
                        ]);
                    }
                }
            }

            if ($this->interessadoId) {
                $interessadoOrigem = Interessado::find($this->interessadoId);

                if ($interessadoOrigem) {
                    $matriculasCriadas = collect($alunosPessoa)->pluck('matricula')->filter()->all();
                    InteressadoMatriculaService::registrarConversao($interessadoOrigem, $matriculasCriadas);
                }
            }

            DB::commit();

            $count = count($alunosPessoa);
            $primeiraMatricula = $alunosPessoa[0]['matricula'] ?? null;

            if ($todosDocsContratoOk) {
                Notification::make()
                    ->title('Matrícula realizada e Contrato emitido!')
                    ->body($count > 1
                        ? "{$count} alunos matriculados e contratos escolares gerados com sucesso (Matrícula Ativa)."
                        : 'Aluno matriculado e contrato escolar gerado com sucesso (Matrícula Ativa).')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Matrícula cadastrada como PENDENTE')
                    ->body('A matrícula foi salva na situação PENDENTE. O Contrato Escolar NÃO foi gerado devido à ausência de documentos obrigatórios de contrato. A matrícula será ativada e o contrato emitido assim que a documentação for completada.')
                    ->warning()
                    ->persistent()
                    ->send();
            }

            // Redirecionar para a edição da primeira matrícula criada
            if ($primeiraMatricula) {
                $this->redirect(route('filament.admin.resources.matriculas.edit', ['record' => $primeiraMatricula->id]));
            } else {
                $this->redirect('/admin/matriculas');
            }

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('Erro ao realizar matrícula')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Busca uma Pessoa existente pelo CPF ou cria uma nova com os dados fornecidos.
     * Se encontrada, atualiza apenas os campos ainda em branco.
     */
    private function buscarOuCriarPessoa(array $dados): Pessoa
    {
        // Usar ID pré-carregado no form (preenchido via auto-fill por CPF)
        if (! empty($dados['pessoa_id_existente'])) {
            $pessoa = Pessoa::find($dados['pessoa_id_existente']);
            if ($pessoa) {
                return $pessoa;
            }
        }

        // Busca por CPF
        $cpf = ! empty($dados['cpf']) ? preg_replace('/\D/', '', $dados['cpf']) : null;
        if ($cpf) {
            $pessoa = Pessoa::where('cpf', $cpf)
                ->orWhereRaw("REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?", [$cpf])
                ->first();

            if ($pessoa) {
                return $pessoa;
            }
        }

        // Busca por nome + e-mail (fallback sem CPF)
        if (empty($cpf) && ! empty($dados['nome']) && ! empty($dados['email'])) {
            $pessoa = Pessoa::where('nome', $dados['nome'])
                ->where('email', $dados['email'])
                ->first();

            if ($pessoa) {
                return $pessoa;
            }
        }

        // Criar nova pessoa
        return Pessoa::create([
            'nome' => $dados['nome'],
            'cpf' => $cpf,
            'data_nascimento' => $dados['data_nascimento'] ?? null,
            'sexo' => $dados['sexo'] ?? null,
            'email' => $dados['email'] ?? null,
            'telefone' => $dados['telefone'] ?? null,
            'nacionalidade_id' => $dados['nacionalidade_id'] ?? null,
            'naturalidade_id' => $dados['naturalidade_id'] ?? null,
            'cor_raca' => $dados['cor_raca'] ?? null,
        ]);
    }

    /**
     * Se a Pessoa era um Interessado ativo no CRM, marca a data de conversão.
     */
    private function marcarConversaoCRM(Pessoa $pessoa): void
    {
        $interessado = Interessado::where('pessoa_id', $pessoa->id)
            ->whereNull('data_conversao')
            ->first();

        if ($interessado) {
            InteressadoMatriculaService::registrarConversao($interessado);
        }
    }

    /**
     * Cria um novo usuário vinculado à Pessoa, atribui o role e envia e-mail de boas-vindas.
     */
    private function criarUsuarioParaPessoa(Pessoa $pessoa, string $email, string $role): void
    {
        $userExistente = User::where('email', $email)->first();

        if ($userExistente) {
            if (! $userExistente->pessoas()->where('pessoa_id', $pessoa->id)->exists()) {
                $userExistente->pessoas()->attach($pessoa->id);
            }

            if (! $userExistente->hasRole($role)) {
                $userExistente->assignRole($role);
            }

            return;
        }

        $usuario = User::create([
            'name' => $pessoa->nome,
            'email' => $email,
            // Senha aleatória e descartada: o acesso se dá pelo link de definição de senha do e-mail de boas-vindas.
            'password' => Hash::make(Str::random(64)),
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);

        $usuario->assignRole($role);
        $usuario->pessoas()->attach($pessoa->id);
        $usuario->notify(new WelcomeUserMail);
    }
}
