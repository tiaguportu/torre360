<?php

namespace App\Filament\Pages;

use App\Models\Cidade;
use App\Models\Contrato;
use App\Models\CorRaca;
use App\Models\Curso;
use App\Models\Endereco;
use App\Models\Matricula;
use App\Models\Pais;
use App\Models\Perfil;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\Sexo;
use App\Models\SituacaoMatricula;
use App\Models\Turma;
use App\Models\Unidade;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

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
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $getPessoaFields = function ($statePath) {
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
                    ->mask('999.999.999-99')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($set, $state, $component) use ($statePath) {
                        if (empty($state)) {
                            return;
                        }

                        $cleanState = preg_replace('/\D/', '', $state);

                        $pessoa = Pessoa::with('endereco')
                            ->where('cpf', $state)
                            ->orWhere('cpf', $cleanState)
                            ->orWhereRaw("REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?", [$cleanState])
                            ->first();

                        if ($pessoa) {
                            $prefix = $statePath ? "{$statePath}." : '';

                            $formData = [
                                "{$prefix}nome" => $pessoa->nome,
                                "{$prefix}data_nascimento" => $pessoa->data_nascimento,
                                "{$prefix}email" => $pessoa->email,
                                "{$prefix}telefone" => $pessoa->telefone,
                                "{$prefix}nacionalidade_id" => (string) $pessoa->nacionalidade_id,
                                "{$prefix}naturalidade_id" => (string) $pessoa->naturalidade_id,
                                "{$prefix}sexo_id" => (string) $pessoa->sexo_id,
                                "{$prefix}cor_raca_id" => (string) $pessoa->cor_raca_id,
                            ];

                            if ($pessoa->endereco) {
                                $formData["{$prefix}cidade_id"] = (string) $pessoa->endereco->cidade_id;
                                $formData["{$prefix}cep"] = $pessoa->endereco->cep;
                                $formData["{$prefix}logradouro"] = $pessoa->endereco->logradouro;
                                $formData["{$prefix}numero"] = $pessoa->endereco->numero;
                                $formData["{$prefix}bairro"] = $pessoa->endereco->bairro;
                            }

                            foreach ($formData as $key => $value) {
                                $set($key, $value);
                            }

                            Notification::make()
                                ->title('Dados carregados')
                                ->body("Pessoa identificada: {$pessoa->nome}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Aviso')
                                ->body('Nenhum registro encontrado para este CPF.')
                                ->warning()
                                ->send();
                        }
                    }),
                TextInput::make('nome')->required()->maxLength(255),
                DatePicker::make('data_nascimento')->label('Data de Nascimento'),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('telefone')->tel()->maxLength(20),
                Select::make('nacionalidade_id')
                    ->label('Nacionalidade')
                    ->options(Pais::pluck('nome', 'id'))
                    ->default(fn () => Pais::where('nome', 'Brasil')->value('id'))
                    ->searchable()
                    ->live(),
                Select::make('naturalidade_id')
                    ->label('Naturalidade')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Cidade::where('nome', 'like', "%{$search}%")->limit(20)->pluck('nome', 'id')->toArray())
                    ->getOptionLabelUsing(fn ($value): ?string => Cidade::find($value)?->nome)
                    ->visible(fn ($get) => $get('nacionalidade_id') == Pais::where('nome', 'Brasil')->value('id')),
                Select::make('sexo_id')
                    ->label('Sexo')
                    ->options(Sexo::pluck('nome', 'id'))
                    ->searchable(),
                Select::make('cor_raca_id')
                    ->label('Cor/Raça')
                    ->options(CorRaca::pluck('nome', 'id'))
                    ->searchable(),
            ];
        };

        $enderecoFields = [
            Select::make('cidade_id')
                ->label('Cidade')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Cidade::selectRaw("id, nome || ' - ' || (SELECT sigla FROM estado WHERE estado.id = cidade.estado_id) as full_nome")
                    ->where('nome', 'like', "%{$search}%")
                    ->limit(20)
                    ->pluck('full_nome', 'id')
                    ->toArray())
                ->getOptionLabelUsing(fn ($value): ?string => Cidade::selectRaw("id, nome || ' - ' || (SELECT sigla FROM estado WHERE estado.id = cidade.estado_id) as full_nome")
                    ->find($value)?->full_nome),
            TextInput::make('cep')->label('CEP'),
            TextInput::make('logradouro')->label('Logradouro'),
            TextInput::make('numero')->label('Número'),
            TextInput::make('bairro')->label('Bairro'),
        ];

        return $schema
            ->components([
                Wizard::make([
                    Step::make('Dados do Aluno')
                        ->description('Identificação básica do estudante')
                        ->icon('heroicon-m-user')
                        ->components([
                            Section::make('Identificação')
                                ->columns(2)
                                ->statePath('aluno')
                                ->schema($getPessoaFields('aluno')),
                            Section::make('Endereço')
                                ->columns(2)
                                ->statePath('aluno')
                                ->schema($enderecoFields),
                        ]),
                    Step::make('Responsável Financeiro')
                        ->description('Quem pagará as mensalidades')
                        ->icon('heroicon-m-credit-card')
                        ->components([
                            Repeater::make('responsaveis')
                                ->label('Responsáveis Financeiros')
                                ->addActionLabel('Adicionar Responsável')
                                ->minItems(1)
                                ->schema([
                                    TextInput::make('parentesco')->label('Vínculo com o Aluno (Ex: Pai, Mãe)')->required()->columnSpanFull(),
                                    TextInput::make('percentual')->label('Percentual de Responsabilidade (%)')->numeric()->default(100)->required()->columnSpanFull(),
                                    Section::make('Identificação do Responsável')
                                        ->columns(2)
                                        ->schema($getPessoaFields(null)),
                                    Section::make('Endereço do Responsável')
                                        ->columns(2)
                                        ->schema($enderecoFields),
                                ]),
                        ]),
                    Step::make('Plano e Matrícula')
                        ->description('Definição de curso e turma')
                        ->icon('heroicon-m-academic-cap')
                        ->components([
                            Section::make()
                                ->columns(2)
                                ->components([
                                    Select::make('unidade_id')
                                        ->label('Unidade / Escola')
                                        ->options(Unidade::pluck('nome', 'id'))
                                        ->searchable()
                                        ->required(),
                                    Select::make('curso_id')
                                        ->label('Curso')
                                        ->options(Curso::pluck('nome_interno', 'id'))
                                        ->live()
                                        ->searchable()
                                        ->required(),
                                    Select::make('turma_id')
                                        ->label('Turma')
                                        ->options(
                                            fn ($get) => Turma::whereHas('serie', fn ($q) => $q->where('curso_id', $get('curso_id')))
                                                ->pluck('nome', 'id')
                                        )
                                        ->searchable()
                                        ->required(),
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

            $alunoData = $raw['aluno'];

            // Criar Endereco do Aluno, se preenchido
            $alunoEnderecoId = null;
            if (! empty($alunoData['logradouro']) || ! empty($alunoData['cidade_id'])) {
                $endereco = Endereco::create([
                    'cidade_id' => $alunoData['cidade_id'] ?? null,
                    'logradouro' => $alunoData['logradouro'] ?? null,
                    'numero' => $alunoData['numero'] ?? null,
                    'bairro' => $alunoData['bairro'] ?? null,
                    'cep' => $alunoData['cep'] ?? null,
                ]);
                $alunoEnderecoId = $endereco->id;
            }

            // 1. Criar Pessoa Aluno
            $aluno = Pessoa::create([
                'nome' => $alunoData['nome'],
                'cpf' => $alunoData['cpf'] ?? null,
                'data_nascimento' => $alunoData['data_nascimento'] ?? null,
                'sexo_id' => $alunoData['sexo_id'] ?? null,
                'email' => $alunoData['email'] ?? null,
                'telefone' => $alunoData['telefone'] ?? null,
                'nacionalidade_id' => $alunoData['nacionalidade_id'] ?? null,
                'naturalidade_id' => $alunoData['naturalidade_id'] ?? null,
                'cor_raca_id' => $alunoData['cor_raca_id'] ?? null,
                'endereco_id' => $alunoEnderecoId,
            ]);

            // Vincular perfis do aluno
            $perfilAluno = Perfil::where('nome', 'Aluno')->first();
            if ($perfilAluno) {
                $aluno->perfis()->syncWithoutDetaching([$perfilAluno->id]);
            }

            // 3. Criar Matrícula
            $matricula = Matricula::create([
                'pessoa_id' => $aluno->id,
                'turma_id' => $raw['turma_id'],
                'situacao_matricula_id' => SituacaoMatricula::first()->id ?? 1, // Ativa por padrão
                'data_matricula' => now(),
            ]);

            // 4. Criar Contrato para a Matrícula
            $contrato = Contrato::create([
                'matricula_id' => $matricula->id,
                'valor_total' => 0, // Valor padrão inicial, pode ser alterado posteriormente
                'data_aceite' => now(),
                'log_assinatura' => 'Gerado automaticamente pelo Assistente de Matrícula',
            ]);

            // Iterar sobre os responsaveis
            $perfilResp = Perfil::where('nome', 'Responsável')->orWhere('nome', 'Responsavel')->first();

            foreach ($raw['responsaveis'] as $respData) {
                // Criar endereco do responsavel
                $respEnderecoId = null;
                if (! empty($respData['logradouro']) || ! empty($respData['cidade_id'])) {
                    $enderecoResp = Endereco::create([
                        'cidade_id' => $respData['cidade_id'] ?? null,
                        'logradouro' => $respData['logradouro'] ?? null,
                        'numero' => $respData['numero'] ?? null,
                        'bairro' => $respData['bairro'] ?? null,
                        'cep' => $respData['cep'] ?? null,
                    ]);
                    $respEnderecoId = $enderecoResp->id;
                }

                // Criar ou buscar pessoa responsável
                $q = Pessoa::query();
                if (! empty($respData['cpf'])) {
                    $q->where('cpf', $respData['cpf']);
                } else {
                    $q->where('nome', $respData['nome'])->where('email', $respData['email']);
                }

                $responsavelPessoa = $q->first();
                if (! $responsavelPessoa) {
                    $responsavelPessoa = Pessoa::create([
                        'nome' => $respData['nome'],
                        'cpf' => $respData['cpf'] ?? null,
                        'data_nascimento' => $respData['data_nascimento'] ?? null,
                        'sexo_id' => $respData['sexo_id'] ?? null,
                        'email' => $respData['email'] ?? null,
                        'telefone' => $respData['telefone'] ?? null,
                        'nacionalidade_id' => $respData['nacionalidade_id'] ?? null,
                        'naturalidade_id' => $respData['naturalidade_id'] ?? null,
                        'cor_raca_id' => $respData['cor_raca_id'] ?? null,
                        'endereco_id' => $respEnderecoId,
                    ]);
                }

                if ($perfilResp) {
                    $responsavelPessoa->perfis()->syncWithoutDetaching([$perfilResp->id]);
                }

                // 5. Criar Vinculo Responsável Financeiro
                ResponsavelFinanceiro::create([
                    'pessoa_id' => $responsavelPessoa->id,
                    'contrato_id' => $contrato->id,
                    'percentual' => $respData['percentual'] ?? 100,
                ]);
            }

            DB::commit();

            Notification::make()
                ->title('Matrícula realizada com sucesso!')
                ->success()
                ->send();

            $this->redirect('/admin/matriculas');

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Erro ao realizar matrícula')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
