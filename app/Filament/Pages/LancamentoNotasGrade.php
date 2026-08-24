<?php

namespace App\Filament\Pages;

use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Services\NotaLancamentoService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class LancamentoNotasGrade extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static UnitEnum|string|null $navigationGroup = 'Avaliações';

    protected static ?string $navigationLabel = 'Lançamento de Notas em Grade';

    protected static ?string $title = 'Lançamento de Notas em Grade';

    protected static ?string $slug = 'avaliacoes/lancamento-notas-grade';

    public ?array $data = [];

    public ?Avaliacao $avaliacaoAtual = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Selecione Turma, Disciplina e Avaliação')
                    ->schema([
                        Select::make('turma_id')
                            ->label('Turma')
                            ->options(function () {
                                $turmaIds = $this->baseAvaliacaoQuery()->pluck('turma_id')->unique();

                                return Turma::query()->whereIn('id', $turmaIds)->orderBy('nome')->pluck('nome', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('disciplina_id', null);
                                $set('avaliacao_id', null);
                                $this->limparGrade();
                            }),
                        Select::make('disciplina_id')
                            ->label('Disciplina')
                            ->options(function (Get $get) {
                                $turmaId = $get('turma_id');

                                if (! $turmaId) {
                                    return [];
                                }

                                $disciplinaIds = $this->baseAvaliacaoQuery()
                                    ->where('turma_id', $turmaId)
                                    ->pluck('disciplina_id')
                                    ->unique();

                                return Disciplina::query()->whereIn('id', $disciplinaIds)->orderBy('nome')->pluck('nome', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->disabled(fn (Get $get) => ! $get('turma_id'))
                            ->afterStateUpdated(function (callable $set) {
                                $set('avaliacao_id', null);
                                $this->limparGrade();
                            }),
                        Select::make('avaliacao_id')
                            ->label('Avaliação')
                            ->options(function (Get $get) {
                                $turmaId = $get('turma_id');
                                $disciplinaId = $get('disciplina_id');

                                if (! $turmaId || ! $disciplinaId) {
                                    return [];
                                }

                                return $this->baseAvaliacaoQuery()
                                    ->where('turma_id', $turmaId)
                                    ->where('disciplina_id', $disciplinaId)
                                    ->with(['categoria', 'etapaAvaliativa'])
                                    ->get()
                                    ->sortBy(fn (Avaliacao $a) => $a->etapaAvaliativa?->id ?? 0)
                                    ->mapWithKeys(fn (Avaliacao $a) => [$a->id => $a->label_exibicao]);
                            })
                            ->searchable()
                            ->live()
                            ->disabled(fn (Get $get) => ! $get('disciplina_id'))
                            ->afterStateUpdated(fn () => $this->carregarGrade()),
                    ])
                    ->columns(3),

                Section::make('Notas dos Alunos')
                    ->visible(fn () => $this->avaliacaoAtual !== null)
                    ->schema([
                        Repeater::make('notas')
                            ->label('')
                            ->schema([
                                TextInput::make('aluno_nome')
                                    ->label('Aluno')
                                    ->disabled()
                                    ->columnSpan(3),
                                Hidden::make('matricula_id'),
                                TextInput::make('valor')
                                    ->label('Nota')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(fn () => $this->avaliacaoAtual?->nota_maxima ?? 10)
                                    ->validationMessages([
                                        'max' => 'A nota não pode ser maior que a nota máxima da avaliação (:max).',
                                    ])
                                    ->columnSpan(1)
                                    ->live()
                                    ->extraInputAttributes(['wire:keydown.enter' => 'salvarNotas']),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salvarNotas')
                ->label('Salvar Notas')
                ->color('primary')
                ->visible(fn () => $this->avaliacaoAtual !== null)
                ->action('salvarNotas'),
        ];
    }

    /**
     * Restringe a listagem de avaliações às do próprio professor, replicando o escopo
     * já aplicado em AvaliacaoResource::getEloquentQuery().
     */
    private function baseAvaliacaoQuery(): Builder
    {
        $query = Avaliacao::query();
        $user = auth()->user();

        if ($user && $user->hasRole('professor')) {
            $query->whereIn('professor_id', $user->pessoas()->pluck('pessoa.id'));
        }

        return $query;
    }

    private function limparGrade(): void
    {
        $this->avaliacaoAtual = null;
        $this->data['notas'] = [];
    }

    public function carregarGrade(): void
    {
        $avaliacaoId = $this->data['avaliacao_id'] ?? null;

        if (! $avaliacaoId) {
            $this->limparGrade();

            return;
        }

        $avaliacao = $this->baseAvaliacaoQuery()->find($avaliacaoId);

        if (! $avaliacao || Gate::forUser(auth()->user())->denies('lancarNotas', $avaliacao)) {
            $this->limparGrade();

            Notification::make()
                ->title('Você não tem permissão para lançar notas nesta avaliação.')
                ->danger()
                ->send();

            return;
        }

        $this->avaliacaoAtual = $avaliacao;

        $this->form->fill([
            'turma_id' => $this->data['turma_id'] ?? null,
            'disciplina_id' => $this->data['disciplina_id'] ?? null,
            'avaliacao_id' => $avaliacaoId,
            'notas' => app(NotaLancamentoService::class)->estadoNotasParaGrade($avaliacao),
        ]);
    }

    public function salvarNotas(): void
    {
        if (! $this->avaliacaoAtual) {
            return;
        }

        Gate::authorize('lancarNotas', $this->avaliacaoAtual);

        $state = $this->form->getState();

        try {
            app(NotaLancamentoService::class)->salvarNotas($this->avaliacaoAtual, $state['notas'] ?? []);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Erro ao salvar nota')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Notas salvas com sucesso!')
            ->success()
            ->send();
    }
}
