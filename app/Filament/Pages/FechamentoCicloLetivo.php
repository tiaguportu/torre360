<?php

namespace App\Filament\Pages;

use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Services\FechamentoCicloService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use UnitEnum;

class FechamentoCicloLetivo extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?string $navigationLabel = 'Fechamento do Ciclo Letivo';

    protected static ?string $title = 'Fechamento do Ciclo Letivo';

    protected static ?string $slug = 'academico/fechamento-ciclo-letivo';

    public ?array $data = [];

    public ?Collection $resultados = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
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

                                $turmaIds = Matricula::query()
                                    ->where('periodo_letivo_id', $get('periodo_letivo_id'))
                                    ->pluck('turma_id')
                                    ->unique();

                                return Turma::query()
                                    ->whereIn('id', $turmaIds)
                                    ->whereIn('tipo_avaliacao', ['notas', 'hibrido'])
                                    ->orderBy('nome')
                                    ->pluck('nome', 'id');
                            })
                            ->searchable()
                            ->disabled(fn (Get $get) => ! $get('periodo_letivo_id')),
                    ])
                    ->columns(2),

                View::make('filament.pages.fechamento-ciclo-letivo-results')
                    ->viewData(fn () => ['resultados' => $this->resultados]),
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
        ];
    }

    public function calcularSituacaoFinal(): void
    {
        $state = $this->form->getState();

        if (! ($state['periodo_letivo_id'] ?? null)) {
            Notification::make()
                ->title('Selecione um período letivo.')
                ->warning()
                ->send();

            return;
        }

        $periodoLetivo = PeriodoLetivo::findOrFail($state['periodo_letivo_id']);

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
}
