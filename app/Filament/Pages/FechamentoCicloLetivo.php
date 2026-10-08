<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\PeriodoLetivo;
use App\Models\SituacaoFinalDisciplina;
use App\Models\Turma;
use App\Services\FechamentoCicloService;
use App\Support\HelpContent;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use UnitEnum;

class FechamentoCicloLetivo extends Page implements HasForms
{
    use HasAjudaAction;
    use HasPageShield;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?string $navigationLabel = 'Fechamento do Ciclo Letivo';

    protected static ?string $title = 'Fechamento do Ciclo Letivo';

    protected static ?string $slug = 'academico/fechamento-ciclo-letivo';

    public ?array $data = [];

    public ?Collection $resultados = null;

    public ?PeriodoLetivo $periodoLetivoSelecionado = null;

    public function mount(): void
    {
        $this->getSchema('content')->fill();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Selecione o Período Letivo')
                    ->description('Consolida as etapas avaliativas de cada disciplina e define a situação final (Aprovado, Recuperação ou Reprovado) das matrículas ativas/concluídas.')
                    ->schema([
                        Select::make('periodo_letivo_id')
                            ->label('Período Letivo')
                            ->options(fn () => PeriodoLetivo::query()->orderByDesc('data_inicio')->pluck('nome', 'id'))
                            ->required()
                            ->live(),
                        Select::make('turma_id')
                            ->label('Turma (opcional — deixe vazio para todas)')
                            ->options(function (Get $get) {
                                if (! $get('periodo_letivo_id')) {
                                    return [];
                                }

                                // Turmas do período que têm matrículas (o período da matrícula é o da turma).
                                return Turma::query()
                                    ->where('periodo_letivo_id', $get('periodo_letivo_id'))
                                    ->whereHas('matriculas')
                                    ->whereIn('tipo_avaliacao', ['notas', 'hibrido'])
                                    ->orderBy('nome')
                                    ->pluck('nome', 'id');
                            })
                            ->searchable()
                            ->disabled(fn (Get $get) => ! $get('periodo_letivo_id')),
                    ])
                    ->columns(2),

                View::make('filament.pages.fechamento-ciclo-letivo-results')
                    ->viewData(fn () => [
                        'resultados' => $this->resultados,
                        'periodoLetivoSelecionado' => $this->periodoLetivoSelecionado,
                        'lancarExameFinalAction' => $this->lancarExameFinalAction,
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calcular')
                ->label('Calcular Situação Final')
                ->icon('heroicon-m-calculator')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Isso irá calcular e gravar a situação final de todas as disciplinas das matrículas ativas/concluídas das turmas selecionadas, substituindo qualquer cálculo anterior. Deseja continuar?')
                ->action('calcularSituacaoFinal'),
            $this->ajudaAction('Fechamento do Ciclo Letivo', HelpContent::make('🏁', 'Fechamento do Ciclo Letivo', 'Consolida as notas e define a situação final de cada aluno.')
                ->passos('🚀 Passo a passo', [
                    'Selecione o Período Letivo.',
                    'Opcionalmente escolha uma turma; vazio significa todas as turmas do período.',
                    'Clique em Calcular Situação Final e confirme.',
                    'Confira a tabela de resultados exibida logo abaixo.',
                ])
                ->secao('📊 Como funciona?', [
                    ['🧮', 'Consolidação', 'Reúne as etapas avaliativas de cada disciplina das matrículas ativas ou concluídas.'],
                    ['🎓', 'Situação final', 'Cada disciplina fica como Aprovado, Recuperação ou Reprovado.'],
                    ['📝', 'Turmas elegíveis', 'Aparecem apenas turmas com avaliação por notas ou híbrida.'],
                ])
                ->alerta('O cálculo substitui qualquer fechamento anterior do mesmo período. Execute apenas com todas as notas lançadas.')),
        ];
    }

    public function calcularSituacaoFinal(): void
    {
        $state = $this->getSchema('content')->getState();

        if (! ($state['periodo_letivo_id'] ?? null)) {
            Notification::make()
                ->title('Selecione um período letivo.')
                ->warning()
                ->send();

            return;
        }

        $periodoLetivo = PeriodoLetivo::findOrFail($state['periodo_letivo_id']);
        $this->periodoLetivoSelecionado = $periodoLetivo;

        $this->resultados = app(FechamentoCicloService::class)
            ->fecharPeriodoLetivo($periodoLetivo, $state['turma_id'] ?? null)
            ->sortBy([
                [fn ($item) => $item->matricula->turma?->nome ?? '', 'asc'],
                [fn ($item) => $item->matricula->pessoa?->nome ?? '', 'asc'],
                [fn ($item) => $item->disciplina->ordem_boletim ?? 0, 'asc'],
            ])
            ->values();

        Notification::make()
            ->title('Fechamento concluído')
            ->body("{$this->resultados->count()} situações finais calculadas e gravadas.")
            ->success()
            ->send();
    }

    public function lancarExameFinalAction(): Action
    {
        return Action::make('lancarExameFinal')
            ->label('Lançar Exame Final')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->size('sm')
            ->modalHeading('Lançar Nota do Exame Final')
            ->modalSubmitActionLabel('Salvar')
            ->form([
                TextInput::make('nota_exame_final')
                    ->label('Nota do Exame Final')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10)
                    ->step(0.01)
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $registroId = (int) ($arguments['registro_id'] ?? 0);

                $registro = $this->resultados?->firstWhere('id', $registroId);

                if (! $registro instanceof SituacaoFinalDisciplina) {
                    Notification::make()
                        ->title('Registro não encontrado. Recalcule a situação final e tente novamente.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    app(FechamentoCicloService::class)->registrarExameFinal($registro, (float) $data['nota_exame_final']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Exame final lançado com sucesso.')
                    ->success()
                    ->send();
            });
    }
}
