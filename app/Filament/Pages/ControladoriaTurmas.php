<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\Curso;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\Unidade;
use App\Services\ControladoriaTurmaService;
use App\Support\HelpContent;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ControladoriaTurmas extends Page implements HasTable
{
    use HasAjudaAction;
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Rentabilidade de Turmas';

    protected static ?string $title = 'Controladoria: Rentabilidade e Ponto de Equilíbrio por Turma';

    protected static ?string $slug = 'financeiro/rentabilidade-turmas';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.controladoria-turmas';

    /**
     * Cache em memória das métricas calculadas pelo service por turma nesta requisição.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $metricasCache = [];

    protected function getControladoriaService(): ControladoriaTurmaService
    {
        return app(ControladoriaTurmaService::class);
    }

    public function getMetricasTurma(Turma $turma): array
    {
        if (! isset($this->metricasCache[$turma->id])) {
            $this->metricasCache[$turma->id] = $this->getControladoriaService()->calcularMetricas($turma);
        }

        return $this->metricasCache[$turma->id];
    }

    public function getConsolidadoProperty(): array
    {
        return $this->getControladoriaService()->calcularConsolidado();
    }

    protected function getHeaderActions(): array
    {
        $conteudo = HelpContent::make(
            '📈',
            'Controladoria Escolar: Ponto de Equilíbrio & Rentabilidade por Turma',
            'Acompanhe em tempo real a saúde financeira de cada sala de aula. Descubra quais turmas geram lucro, quais estão no limite e quantas matrículas faltam para atingir o ponto de equilíbrio (break-even).'
        )
            ->secao('🎯 O que você pode fazer?', [
                ['📊', 'Visão Consolidada', 'Consulte no topo a receita líquida total, custos docentes e operacionais, margem média e o total de turmas saudáveis.'],
                ['⚖️', 'Ponto de Equilíbrio (Break-Even)', 'O sistema calcula automaticamente quantos alunos pagantes cada turma precisa para cobrir todos os seus custos diretos e rateados.'],
                ['⚙️', 'Ajustar Custos da Turma', 'Defina a mensalidade de tabela, o custo docente mensal da sala e as despesas operacionais rateadas pela ação de linha.'],
                ['🚀', 'Simulador de Cenários', 'Teste o impacto imediato de captar novos alunos ou aplicar reajustes de mensalidade na margem da turma.'],
            ])
            ->dica('Turmas com margem negativa (em vermelho) operam em prejuízo. Considere ações de captação direcionada ou remanejamento de horários.');

        return [
            $this->ajudaAction('Rentabilidade de Turmas', $conteudo),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Turma::query()
                    ->with(['serie.curso.unidade', 'turno', 'matriculas'])
                    ->orderBy('nome')
            )
            ->columns([
                TextColumn::make('nome')
                    ->label('Turma')
                    ->description(fn (Turma $record) => ($record->serie?->nome ?? '').' • '.($record->turno?->nome ?? 'Turno não inf.'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ocupacao')
                    ->label('Ocupação')
                    ->state(function (Turma $record): string {
                        $m = $this->getMetricasTurma($record);

                        return "{$m['alunos_ativos']} / {$m['vagas_maximas']} ({$m['taxa_ocupacao']}%)";
                    })
                    ->badge()
                    ->color(function (Turma $record): string {
                        $m = $this->getMetricasTurma($record);

                        return match (true) {
                            $m['taxa_ocupacao'] >= 80 => 'success',
                            $m['taxa_ocupacao'] >= 50 => 'warning',
                            default => 'danger',
                        };
                    }),

                TextColumn::make('receita_liquida')
                    ->label('Receita Mensal')
                    ->state(fn (Turma $record) => 'R$ '.number_format($this->getMetricasTurma($record)['receita_liquida'], 2, ',', '.'))
                    ->description(fn (Turma $record) => 'Ticket: R$ '.number_format($this->getMetricasTurma($record)['ticket_medio'], 2, ',', '.'))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('mensalidade_base', $direction)),

                TextColumn::make('custo_total')
                    ->label('Custo Total')
                    ->state(fn (Turma $record) => 'R$ '.number_format($this->getMetricasTurma($record)['custo_total'], 2, ',', '.'))
                    ->description(fn (Turma $record) => 'Docente: R$ '.number_format($this->getMetricasTurma($record)['custo_docente'], 2, ',', '.')),

                TextColumn::make('resultado_mensal')
                    ->label('Resultado / Margem')
                    ->state(function (Turma $record): string {
                        $m = $this->getMetricasTurma($record);
                        $sinal = $m['resultado_mensal'] >= 0 ? '+' : '';

                        return $sinal.'R$ '.number_format($m['resultado_mensal'], 2, ',', '.')." ({$m['margem_percentual']}%)";
                    })
                    ->badge()
                    ->color(function (Turma $record): string {
                        $status = $this->getMetricasTurma($record)['status'];

                        return match ($status) {
                            'lucrativa' => 'success',
                            'alerta' => 'warning',
                            'deficitaria' => 'danger',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('ponto_equilibrio')
                    ->label('Ponto de Equilíbrio')
                    ->state(function (Turma $record): string {
                        $m = $this->getMetricasTurma($record);
                        $saldo = $m['saldo_alunos_equilibrio'];
                        $saldoTexto = $saldo >= 0 ? "+{$saldo} alunos" : "{$saldo} alunos";

                        return "{$m['ponto_equilibrio_alunos']} alunos ({$saldoTexto})";
                    })
                    ->description(function (Turma $record): string {
                        $saldo = $this->getMetricasTurma($record)['saldo_alunos_equilibrio'];

                        return $saldo >= 0 ? 'Meta atingida' : 'Faltam matrículas';
                    })
                    ->color(fn (Turma $record) => $this->getMetricasTurma($record)['saldo_alunos_equilibrio'] >= 0 ? 'success' : 'danger'),

                TextColumn::make('status')
                    ->label('Diagnóstico')
                    ->state(function (Turma $record): string {
                        return match ($this->getMetricasTurma($record)['status']) {
                            'lucrativa' => '🟢 Lucrativa',
                            'alerta' => '🟡 No Limite',
                            'deficitaria' => '🔴 Deficitária',
                            default => '—',
                        };
                    }),
            ])
            ->filters([
                SelectFilter::make('periodo_letivo_id')
                    ->label('Período Letivo')
                    ->options(fn () => PeriodoLetivo::pluck('nome', 'id')->toArray()),

                SelectFilter::make('unidade_id')
                    ->label('Unidade Escolar')
                    ->options(fn () => Unidade::pluck('nome', 'id')->toArray())
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereHas('serie.curso', fn ($q) => $q->where('unidade_id', $data['value']))
                        : $query),

                SelectFilter::make('curso_id')
                    ->label('Curso / Nível')
                    ->options(fn () => Curso::pluck('nome_externo', 'id')->toArray())
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereHas('serie', fn ($q) => $q->where('curso_id', $data['value']))
                        : $query),

                Filter::make('apenas_deficitarias')
                    ->label('Apenas Turmas em Prejuízo')
                    ->query(fn (Builder $query) => $query), // O filtro visual é destacado nos cards
            ])
            ->recordActions([
                Action::make('parametros_custos')
                    ->label('Custos da Turma')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('gray')
                    ->modalHeading(fn (Turma $record) => "Parâmetros Financeiros — {$record->nome}")
                    ->modalDescription('Defina os custos mensais diretos e indiretos para apuração do ponto de equilíbrio.')
                    ->fillForm(fn (Turma $record): array => [
                        'mensalidade_base' => $record->mensalidade_base,
                        'custo_docente_mensal' => $record->custo_docente_mensal,
                        'custo_operacional_rateado' => $record->custo_operacional_rateado,
                        'meta_margem_lucro' => $record->meta_margem_lucro ?? 20.0,
                    ])
                    ->form([
                        Grid::make(2)->schema([
                            TextInput::make('mensalidade_base')
                                ->label('Mensalidade de Tabela (R$)')
                                ->numeric()
                                ->prefix('R$')
                                ->helperText('Valor cobrado por aluno caso não haja valor específico no contrato'),
                            TextInput::make('meta_margem_lucro')
                                ->label('Meta de Margem de Lucro (%)')
                                ->numeric()
                                ->suffix('%')
                                ->default(20.0),
                            TextInput::make('custo_docente_mensal')
                                ->label('Custo Docente Mensal (R$)')
                                ->numeric()
                                ->prefix('R$')
                                ->required()
                                ->helperText('Salários + encargos dos professores alocados nesta turma'),
                            TextInput::make('custo_operacional_rateado')
                                ->label('Custo Operacional Rateado (R$)')
                                ->numeric()
                                ->prefix('R$')
                                ->required()
                                ->helperText('Rateio de água, luz, aluguel, limpeza e secretaria'),
                        ]),
                    ])
                    ->action(function (Turma $record, array $data): void {
                        $record->update($data);
                        unset($this->metricasCache[$record->id]);
                        Notification::make()
                            ->title('Parâmetros Financeiros Atualizados')
                            ->success()
                            ->send();
                    }),

                Action::make('simular')
                    ->label('Simular Cenário')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->modalHeading(fn (Turma $record) => "Simulador de Cenários — {$record->nome}")
                    ->modalDescription('Projete o resultado financeiro adicionando alunos ou aplicando reajustes.')
                    ->form([
                        Grid::make(3)->schema([
                            TextInput::make('novos_alunos')
                                ->label('Variação de Alunos')
                                ->numeric()
                                ->default(2)
                                ->helperText('Ex: +2 para captar 2 alunos ou -1 para perda de aluno'),
                            TextInput::make('reajuste_mensalidade_pct')
                                ->label('Reajuste de Mensalidade (%)')
                                ->numeric()
                                ->suffix('%')
                                ->default(5.0)
                                ->helperText('Ex: 5% de reajuste anual'),
                            TextInput::make('variacao_custo_docente')
                                ->label('Variação Custo Docente (R$)')
                                ->numeric()
                                ->prefix('R$')
                                ->default(0.0)
                                ->helperText('Ex: contratação de monitor ou aumento de horas'),
                        ]),
                    ])
                    ->action(function (Turma $record, array $data): void {
                        $resultado = $this->getControladoriaService()->simularCenario(
                            $record,
                            (int) ($data['novos_alunos'] ?? 0),
                            (float) ($data['reajuste_mensalidade_pct'] ?? 0.0),
                            (float) ($data['variacao_custo_docente'] ?? 0.0)
                        );

                        $sim = $resultado['simulado'];
                        $difFormatada = ($sim['variacao_resultado'] >= 0 ? '+' : '')
                            .'R$ '.number_format($sim['variacao_resultado'], 2, ',', '.');

                        Notification::make()
                            ->title("Projeção para {$record->nome}: {$sim['alunos']} Alunos")
                            ->body(
                                'Receita Líquida: R$ '.number_format($sim['receita_liquida'], 2, ',', '.')
                                ."\nNovo Resultado: R$ ".number_format($sim['resultado_mensal'], 2, ',', '.')
                                ." (Margem: {$sim['margem_percentual']}%)"
                                ."\nImpacto no Caixa: {$difFormatada}/mês"
                                ."\nNovo Ponto de Equilíbrio: {$sim['ponto_equilibrio_alunos']} alunos."
                            )
                            ->color($sim['resultado_mensal'] >= 0 ? 'success' : 'danger')
                            ->persistent()
                            ->send();
                    }),

                Action::make('ver_matriculas')
                    ->label('Alunos')
                    ->icon('heroicon-o-academic-cap')
                    ->color('gray')
                    ->url(fn (Turma $record) => url("/admin/matriculas?tableFilters[turma_id][value]={$record->id}")),
            ])
            ->stackedOnMobile();
    }
}
