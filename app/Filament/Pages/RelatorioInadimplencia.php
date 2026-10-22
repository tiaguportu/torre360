<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\Fatura;
use App\Models\Turma;
use App\Services\EncargosAtrasoService;
use App\Support\HelpContent;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Consolida as faturas em atraso por turma, faixa de atraso e responsável — o
 * acompanhamento de inadimplência que faltava além da Régua de Cobrança (que dispara
 * lembretes, mas não mostra o quadro consolidado).
 */
class RelatorioInadimplencia extends Page implements HasTable
{
    use HasAjudaAction;
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Relatório de Inadimplência';

    protected static ?string $title = 'Relatório de Inadimplência';

    protected static ?string $slug = 'financeiro/relatorio-inadimplencia';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.relatorio-inadimplencia';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFaturasAtrasadasQuery())
            ->columns([
                TextColumn::make('contrato.matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable(),
                TextColumn::make('contrato.matricula.turma.nome')
                    ->label('Turma')
                    ->sortable(),
                TextColumn::make('responsaveis')
                    ->label('Responsável(is)')
                    ->state(fn (Fatura $record) => $record->contrato?->responsaveisFinanceiros
                        ->pluck('pessoa.nome')
                        ->filter()
                        ->implode(', ') ?: '—'),
                TextColumn::make('vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('dias_atraso')
                    ->label('Dias em Atraso')
                    ->state(fn (Fatura $record) => max(0, (int) Carbon::parse($record->vencimento)->diffInDays(now(), false)))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state <= 7 => 'warning',
                        $state <= 30 => 'danger',
                        default => 'gray',
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('vencimento', $direction === 'asc' ? 'desc' : 'asc')),
                TextColumn::make('valor_restante')
                    ->label('Saldo Devedor')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('encargos')
                    ->label('Multa e Juros')
                    ->state(fn (Fatura $record): float => ($encargos = app(EncargosAtrasoService::class)->calcular($record))['multa'] + $encargos['juros'])
                    ->money('BRL')
                    ->color('danger'),
            ])
            ->filters([
                SelectFilter::make('turma')
                    ->label('Turma')
                    ->options(fn () => Turma::orderBy('nome')->pluck('nome', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $turmaId) => $q->whereHas('contrato.matricula', fn ($mq) => $mq->where('turma_id', $turmaId))
                    )),
                Filter::make('faixa_atraso')
                    ->label('Faixa de Atraso')
                    ->form([
                        FormSelect::make('faixa')
                            ->label('Faixa')
                            ->options([
                                '1-7' => '1 a 7 dias',
                                '8-15' => '8 a 15 dias',
                                '16-30' => '16 a 30 dias',
                                '31+' => 'Mais de 30 dias',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['faixa'] ?? null) {
                            '1-7' => $query->whereBetween('vencimento', [now()->subDays(7)->toDateString(), now()->subDay()->toDateString()]),
                            '8-15' => $query->whereBetween('vencimento', [now()->subDays(15)->toDateString(), now()->subDays(8)->toDateString()]),
                            '16-30' => $query->whereBetween('vencimento', [now()->subDays(30)->toDateString(), now()->subDays(16)->toDateString()]),
                            '31+' => $query->where('vencimento', '<', now()->subDays(30)->toDateString()),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('vencimento', 'asc')
            ->stackedOnMobile();
    }

    /**
     * Em atraso = marcada como Atrasado ou Pendente/Parcial com vencimento passado (nenhuma rotina troca o status
     * sozinha; antes só a marcação manual aparecia aqui).
     */
    protected function getFaturasAtrasadasQuery(): Builder
    {
        return Fatura::query()
            ->emAtraso()
            ->with([
                'contrato.matricula.pessoa',
                'contrato.matricula.turma',
                'contrato.responsaveisFinanceiros.pessoa',
                'itens',
                'transacoes',
            ]);
    }

    /**
     * @return array{total_faturas: int, total_devido: float, total_encargos: float, total_atualizado: float, total_responsaveis: int}
     */
    public function getResumo(): array
    {
        $faturas = $this->getFaturasAtrasadasQuery()->get();
        $encargos = app(EncargosAtrasoService::class);
        $totais = $encargos->totais($faturas->map(fn (Fatura $f): array => $encargos->calcular($f)));

        return [
            'total_faturas' => $faturas->count(),
            'total_devido' => (float) $faturas->sum('valor_restante'),
            'total_encargos' => $totais['encargos'],
            'total_atualizado' => round((float) $faturas->sum('valor_restante') + $totais['encargos'], 2),
            'total_responsaveis' => $faturas
                ->flatMap(fn (Fatura $f) => $f->contrato?->responsaveisFinanceiros->pluck('pessoa_id') ?? [])
                ->unique()
                ->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Relatório de Inadimplência', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('⚠️', 'Relatório de Inadimplência', 'Faturas em atraso, consolidadas por turma, faixa de atraso e responsável.')
            ->secao('🎯 O que você encontra aqui?', [
                ['📋', 'Faturas em atraso', 'Aluno, turma, responsável(is), vencimento, dias em atraso, saldo devedor e a multa e os juros estimados. Entra a fatura marcada como atrasada e também a pendente ou parcial com o vencimento já passado.'],
                ['🔎', 'Filtros', 'Filtre por turma ou por faixa de dias de atraso.'],
                ['🧮', 'Multa e juros', 'Usam os percentuais do contrato (ou o padrão da escola): multa de no máximo 2%, uma vez, e juros simples proporcionais aos dias. Vencimento em fim de semana ou feriado vale no próximo dia útil.'],
            ])
            ->dica('Para lembretes automáticos de cobrança, use a Régua de Cobrança — este relatório só mostra o quadro consolidado.');
    }
}
