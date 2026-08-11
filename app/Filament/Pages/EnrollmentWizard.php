<?php

namespace App\Filament\Pages;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use App\Enums\SituacaoMatricula;
use App\Models\AlunoResponsavel;
use App\Models\Cidade;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Endereco;
use App\Models\Interessado;
use App\Models\Matricula;
use App\Models\Pais;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Notifications\WelcomeUserMail;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

    public function mount(): void
    {
        $this->form->fill([
            'data_ativacao' => now()->toDateString(),
            'situacao' => SituacaoMatricula::ATIVA->value,
            'periodo_letivo_id' => PeriodoLetivo::latest('id')->value('id'),
        ]);
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
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>O <strong>Assistente de Matrícula</strong> guia você em 3 etapas para cadastrar um ou mais alunos de uma mesma família, criando automaticamente as matrículas, contratos e vínculos de responsabilidade.</p>';

        $html .= '<h3>Etapas</h3><ol>';
        $html .= '<li><strong>Dados do(s) Aluno(s):</strong> Adicione quantos filhos forem necessários. Digite o CPF para buscar automaticamente um cadastro já existente. Preencha nome, data de nascimento, endereço e, se desejar, crie um acesso de portal para o aluno.</li>';
        $html .= '<li><strong>Pais / Responsáveis:</strong> Cadastre os responsáveis da família. O CPF também busca cadastros existentes. Defina o vínculo (Pai, Mãe, Avó, etc.) e se é responsável financeiro. Os responsáveis serão vinculados a <em>todos</em> os alunos adicionados na etapa anterior.</li>';
        $html .= '<li><strong>Plano e Matrícula:</strong> Selecione a unidade, o período letivo, o curso e a turma. Defina a situação inicial (Ativa ou Pendente) e a data de ativação. As turmas são filtradas automaticamente pela unidade e pelo curso escolhidos.</li>';
        $html .= '</ol>';

        $html .= '<h3>Dicas importantes</h3><ul>';
        $html .= '<li>Se o CPF já está cadastrado, os dados são preenchidos automaticamente — você só precisa revisar.</li>';
        $html .= '<li>Se o aluno ou responsável já era um <strong>Interessado no CRM</strong>, a conversão será registrada automaticamente.</li>';
        $html .= '<li>A turma mostra a quantidade de vagas disponíveis. Não é possível matricular em turma lotada.</li>';
        $html .= '<li>O campo <strong>Percentual</strong> do responsável financeiro define a divisão do contrato (ex: dois responsáveis com 50% cada).</li>';
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
                            $set('data_nascimento', $pessoa->data_nascimento?->toDateString());
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
                    ->dehydrated(),

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

                                            return Turma::whereHas('serie', fn ($q) => $q->where('curso_id', $cursoId))
                                                ->when($periodoLetivoId, fn ($q) => $q->where('periodo_letivo_id', $periodoLetivoId))
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

            $turma = Turma::with('serie')->find($raw['turma_id']);

            // ── Validação de vagas ──────────────────────────────────────
            if ($turma && $turma->vagas_maximas) {
                $alunosCount = count($raw['alunos']);
                $matriculadas = $turma->matriculas()->count();

                if (($matriculadas + $alunosCount) > $turma->vagas_maximas) {
                    $disponiveis = max(0, $turma->vagas_maximas - $matriculadas);
                    Notification::make()
                        ->title('Turma sem vagas suficientes')
                        ->body("A turma \"{$turma->nome}\" possui apenas {$disponiveis} vaga(s) disponível(is) e você tentou matricular {$alunosCount} aluno(s).")
                        ->danger()
                        ->send();

                    return;
                }
            }

            /** @var list<array{aluno: Pessoa, contrato: Contrato}> $alunosPessoa */
            $alunosPessoa = [];

            foreach ($raw['alunos'] as $alunoData) {

                // ── Buscar ou criar Pessoa Aluno ────────────────────────
                $aluno = $this->buscarOuCriarPessoa($alunoData);

                // ── Endereço do Aluno (apenas se não existia antes) ─────
                if (! $alunoData['pessoa_id_existente'] && (! empty($alunoData['logradouro']) || ! empty($alunoData['cidade_id']))) {
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
                    'situacao' => $raw['situacao'] ?? SituacaoMatricula::ATIVA->value,
                    'periodo_letivo_id' => $raw['periodo_letivo_id'] ?? $turma?->periodo_letivo_id,
                    'data_ativacao' => $raw['data_ativacao'] ?? null,
                ]);

                // ── Criar Contrato para a Matrícula ─────────────────────
                $contrato = Contrato::create([
                    'matricula_id' => $matricula->id,
                    'valor_total' => 0,
                    'data_aceite' => now(),
                    'log_assinatura' => 'Gerado automaticamente pelo Assistente de Matrícula',
                ]);

                // ── Integração CRM: marcar conversão ────────────────────
                $this->marcarConversaoCRM($aluno);

                $alunosPessoa[] = ['aluno' => $aluno, 'contrato' => $contrato, 'matricula' => $matricula];
            }

            // ── Responsáveis ────────────────────────────────────────────
            foreach ($raw['responsaveis'] as $respData) {
                $responsavelPessoa = $this->buscarOuCriarPessoa($respData);

                // Endereço do responsável (apenas se não existia antes)
                if (! $respData['pessoa_id_existente'] && (! empty($respData['logradouro']) || ! empty($respData['cidade_id']))) {
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

                    if ($respData['is_financeiro'] ?? false) {
                        ResponsavelFinanceiro::create([
                            'pessoa_id' => $responsavelPessoa->id,
                            'contrato_id' => $contratoObj->id,
                            'percentual' => $respData['percentual'] ?? 100,
                        ]);
                    }
                }
            }

            DB::commit();

            $count = count($alunosPessoa);
            $primeiraMatricula = $alunosPessoa[0]['matricula'] ?? null;

            Notification::make()
                ->title('Matrícula realizada com sucesso!')
                ->body($count > 1
                    ? "{$count} alunos matriculados com sucesso."
                    : 'Aluno matriculado com sucesso.')
                ->success()
                ->send();

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
            $interessado->update(['data_conversao' => now()]);
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

        $senha = Str::password(12, true, true, false);

        $usuario = User::create([
            'name' => $pessoa->nome,
            'email' => $email,
            'password' => Hash::make($senha),
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);

        $usuario->assignRole($role);
        $usuario->pessoas()->attach($pessoa->id);
        $usuario->notify(new WelcomeUserMail($senha));
    }
}
