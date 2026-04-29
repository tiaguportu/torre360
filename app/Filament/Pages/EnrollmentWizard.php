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
use App\Models\Matricula;
use App\Models\Pais;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Unidade;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
                                "{$prefix}sexo" => $pessoa->sexo?->value,
                                "{$prefix}cor_raca" => $pessoa->cor_raca?->value,
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
                    'complemento' => $alunoData['complemento'] ?? null,
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
                'sexo' => $alunoData['sexo'] ?? null,
                'email' => $alunoData['email'] ?? null,
                'telefone' => $alunoData['telefone'] ?? null,
                'nacionalidade_id' => $alunoData['nacionalidade_id'] ?? null,
                'naturalidade_id' => $alunoData['naturalidade_id'] ?? null,
                'cor_raca' => $alunoData['cor_raca'] ?? null,
            ]);

            if ($alunoEnderecoId) {
                $aluno->enderecos()->attach($alunoEnderecoId);
            }

            // 3. Criar Matrícula
            $matricula = Matricula::create([
                'pessoa_id' => $aluno->id,
                'turma_id' => $raw['turma_id'],
                'situacao' => SituacaoMatricula::ATIVA,
                'data_matricula' => now(),
            ]);

            // 4. Criar Contrato para a Matrícula
            $contrato = Contrato::create([
                'valor_total' => 0, // Valor padrão inicial, pode ser alterado posteriormente
                'data_aceite' => now(),
                'log_assinatura' => 'Gerado automaticamente pelo Assistente de Matrícula',
            ]);

            // Vincular contrato à matrícula
            $matricula->update(['contrato_id' => $contrato->id]);

            foreach ($raw['responsaveis'] as $respData) {
                // Criar endereco do responsavel
                $respEnderecoId = null;
                if (! empty($respData['logradouro']) || ! empty($respData['cidade_id'])) {
                    $enderecoResp = Endereco::create([
                        'cidade_id' => $respData['cidade_id'] ?? null,
                        'logradouro' => $respData['logradouro'] ?? null,
                        'numero' => $respData['numero'] ?? null,
                        'complemento' => $respData['complemento'] ?? null,
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
                        'sexo' => $respData['sexo'] ?? null,
                        'email' => $respData['email'] ?? null,
                        'telefone' => $respData['telefone'] ?? null,
                        'nacionalidade_id' => $respData['nacionalidade_id'] ?? null,
                        'naturalidade_id' => $respData['naturalidade_id'] ?? null,
                        'cor_raca' => $respData['cor_raca'] ?? null,
                    ]);

                    if ($respEnderecoId) {
                        $responsavelPessoa->enderecos()->attach($respEnderecoId);
                    }
                }

                // 5. Criar Vinculo Aluno-Responsável
                AlunoResponsavel::create([
                    'aluno_id' => $aluno->id,
                    'responsavel_id' => $responsavelPessoa->id,
                    'tipo_vinculo_id' => $respData['tipo_vinculo_id'],
                ]);

                // 6. Criar Vinculo Responsável Financeiro (se marcado)
                if ($respData['is_financeiro'] ?? false) {
                    ResponsavelFinanceiro::create([
                        'pessoa_id' => $responsavelPessoa->id,
                        'contrato_id' => $contrato->id,
                        'percentual' => $respData['percentual'] ?? 100,
                    ]);
                }
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
