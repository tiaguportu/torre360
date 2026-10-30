<?php

namespace App\Filament\Pages;

use App\Enums\SituacaoNota;
use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\VideoTutorial;
use App\Services\MediaParcialService;
use App\Services\NotaLancamentoService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

    /**
     * Cache da requisição (propriedade protegida: o Livewire não a leva de uma requisição para a outra).
     *
     * @var array{avaliacao: Avaliacao, avaliacoes: Collection<int, Avaliacao>, notas: Collection<int, Collection<int, Nota>>, periodo: PeriodoLetivo}|null
     */
    protected ?array $contextoMedias = null;

    /**
     * @var array<string, array{texto: string, cor: string, dica: string}>
     */
    protected array $mediasDasLinhas = [];

    public function mount(): void
    {
        $this->getSchema('content')->fill();
    }

    public function content(Schema $schema): Schema
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
                                    ->with(['categoria', 'etapaAvaliativa', 'turma', 'disciplina'])
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
                    ->description(fn (): ?string => ($contexto = $this->contextoMedias()) ? app(MediaParcialService::class)->legenda($contexto) : null)
                    ->visible(fn () => $this->avaliacaoAtual !== null)
                    ->schema([
                        Repeater::make('notas')
                            ->label('')
                            ->schema([
                                TextInput::make('aluno_nome')
                                    ->label('Aluno')
                                    ->disabled()
                                    ->columnSpan(['default' => 2, 'md' => 2]),
                                Hidden::make('matricula_id'),
                                Select::make('situacao')
                                    ->label('Situação')
                                    ->options(SituacaoNota::class)
                                    ->placeholder('Com nota')
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, $state) => $state ? $set('valor', null) : null)
                                    ->columnSpan(['default' => 2, 'md' => 1]),
                                TextInput::make('valor')
                                    ->label('Nota')
                                    ->disabled(fn (Get $get) => filled($get('situacao')))
                                    ->dehydrated()
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->minValue(0)
                                    ->maxValue(fn () => $this->avaliacaoAtual?->nota_maxima ?? 10)
                                    ->validationMessages([
                                        'max' => 'A nota não pode ser maior que a nota máxima da avaliação (:max).',
                                    ])
                                    ->columnSpan(1)
                                    ->live(debounce: 400)
                                    ->extraInputAttributes(['wire:keydown.enter' => 'salvarNotas']),
                                // Só leitura: a média do aluno na etapa com a nota digitada, antes de salvar.
                                TextEntry::make('media_parcial')
                                    ->label('Média parcial')
                                    ->state(fn (Get $get): string => $this->mediaDaLinha($get)['texto'])
                                    ->badge()
                                    ->color(fn (Get $get): string => $this->mediaDaLinha($get)['cor'])
                                    ->tooltip(fn (Get $get): string => $this->mediaDaLinha($get)['dica'])
                                    ->columnSpan(['default' => 'full', 'md' => 1]),
                            ])
                            ->columns(['default' => 3, 'md' => 5])
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
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Lançamento de Notas em Grade')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'avaliacoes-notas')->orderBy('ordem')->first(),
                        ]),
                ]),
            Action::make('salvarNotas')
                ->label('Salvar Notas')
                ->color('primary')
                ->visible(fn () => $this->avaliacaoAtual !== null)
                ->action('salvarNotas'),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>Esta página permite lançar notas de forma rápida, em formato de planilha, para todos os alunos de uma avaliação de uma só vez.</p>';
        $html .= '<h3>Como usar?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Turma → Disciplina → Avaliação:</strong> Selecione em sequência para carregar a grade de alunos. A Avaliação já precisa ter sido cadastrada antes, em "Avaliações".</li>';
        $html .= '<li><strong>Grade de Notas:</strong> Digite a nota de cada aluno; o valor máximo é validado conforme a nota máxima configurada na avaliação.</li>';
        $html .= '<li><strong>Média parcial:</strong> A coluna ao lado da nota mostra a média do aluno na disciplina, dentro da etapa, já com a nota que você digitou e antes de salvar. Verde: a partir da nota de aprovação do período letivo; âmbar: a partir da nota de recuperação; vermelho: abaixo disso. É uma projeção: a situação final sai no Fechamento do Ciclo.</li>';
        $html .= '<li><strong>Salvar Notas:</strong> Clique no botão para gravar todas as notas lançadas de uma vez, ou pressione Enter em qualquer campo.</li>';
        $html .= '<li><strong>Situação (Faltou / Não se aplica):</strong> Para avaliações aplicadas só a alguns alunos, ou quando o aluno faltou, escolha a situação no lugar de digitar a nota. O aluno deixa de constar como pendência de lançamento e a avaliação não entra na média dele.</li>';
        $html .= '<li><strong>Deixar em branco:</strong> Remove a nota do aluno, caso já exista uma lançada. Sem nota e sem situação, o aluno continua como pendente.</li>';
        $html .= '</ul>';

        return $html;
    }

    /**
     * Restringe a listagem de avaliações às do próprio professor, replicando o escopo
     * já aplicado em AvaliacaoResource::getEloquentQuery().
     */
    private function baseAvaliacaoQuery(): Builder
    {
        $query = Avaliacao::query();
        $user = auth()->user();

        if ($user && $user->atuaComoProfessor()) {
            $query->whereIn('professor_id', $user->pessoas()->pluck('pessoa.id'));
        }

        return $query;
    }

    private function limparGrade(): void
    {
        $this->avaliacaoAtual = null;
        $this->data['notas'] = [];
        $this->esquecerMedias();
    }

    /**
     * Contexto da média parcial, montado uma vez por requisição (duas consultas para todas as linhas da grade).
     *
     * @return array{avaliacao: Avaliacao, avaliacoes: Collection<int, Avaliacao>, notas: Collection<int, Collection<int, Nota>>, periodo: PeriodoLetivo}|null
     */
    private function contextoMedias(): ?array
    {
        if (! $this->avaliacaoAtual) {
            return null;
        }

        return $this->contextoMedias ??= app(MediaParcialService::class)->contexto($this->avaliacaoAtual);
    }

    private function esquecerMedias(): void
    {
        $this->contextoMedias = null;
        $this->mediasDasLinhas = [];
    }

    /**
     * Texto, cor (verde, âmbar ou vermelho, pela regra do fechamento do ciclo) e dica da média parcial de uma linha.
     * O cálculo roda uma vez por linha e por requisição: texto, cor e dica leem o mesmo resultado.
     *
     * @return array{texto: string, cor: string, dica: string}
     */
    private function mediaDaLinha(Get $get): array
    {
        $contexto = $this->contextoMedias();
        $matriculaId = (int) $get('matricula_id');

        if ($contexto === null || $matriculaId === 0) {
            return ['texto' => '—', 'cor' => 'gray', 'dica' => 'Sem média'];
        }

        // Faltou / Não se aplica não tem valor: a nota da avaliação aberta sai da conta.
        $valor = filled($get('situacao')) ? null : $get('valor');
        $chave = $matriculaId.'|'.json_encode($valor);

        if (isset($this->mediasDasLinhas[$chave])) {
            return $this->mediasDasLinhas[$chave];
        }

        $servico = app(MediaParcialService::class);
        $media = $servico->media($contexto, $matriculaId, $valor);
        $faixa = $servico->situacao($contexto, $media);

        return $this->mediasDasLinhas[$chave] = [
            'texto' => $media === null ? '—' : $servico->formatar($media),
            'cor' => $faixa ? (string) $faixa->getColor() : 'gray',
            'dica' => $faixa ? "{$faixa->getLabel()}, se a média se mantiver" : 'Sem notas lançadas nesta etapa',
        ];
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
        $this->esquecerMedias();

        $this->getSchema('content')->fill([
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

        $state = $this->getSchema('content')->getState();

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
