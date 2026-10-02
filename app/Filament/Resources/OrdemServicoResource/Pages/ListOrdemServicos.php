<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\OrdemServicoResource;
use App\Support\HelpContent;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListOrdemServicos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = OrdemServicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->ajudaAction('Ordens de Serviço', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🛠️', 'Ordens de Serviço', 'Solicitações de manutenção e serviços da instituição.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja título, status, prioridade, prazo de conclusão, custo estimado e percentual concluído.'],
                ['📊', 'Indicadores', 'Os cartões no topo mostram OS por status, OS atrasadas e custo estimado total.'],
                $user->can('Create:OrdemServico') ? ['🆕', 'Nova OS', 'Abra uma ordem informando título, prioridade, prazo e custo estimado.'] : null,
                $user->can('Update:OrdemServico') ? ['✏️', 'Editar', 'Atualize status, andamento e custos da ordem.'] : null,
                ['📄', 'Exportar PDF', 'Marque uma ou mais ordens na lista e use a ação em lote "Exportar PDF".'],
                ['🔎', 'Filtros', 'Filtre por status e por prioridade.'],
            ])
            ->dica('Ordens com prazo vencido que não estão concluídas nem canceladas contam como atrasadas. Os cartões acompanham os filtros e a busca da lista.');
    }

    /**
     * Cartões de resumo no topo da lista, renderizados na própria página (sem componente Livewire
     * filho com props reativas), acompanhando a busca e os filtros em uso.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getResumoContentComponent(),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getResumoContentComponent(): Component
    {
        return Section::make()
            ->schema(fn (): array => $this->getResumoStats())
            ->columns(['default' => 1, 'md' => 3])
            ->contained(false)
            ->gridContainer();
    }

    /**
     * @return array<int, Stat>
     */
    public function getResumoStats(): array
    {
        $query = $this->getFilteredTableQuery()->reorder();

        $abertas = (clone $query)->where('status', 'Aberta')->count();
        $emAndamento = (clone $query)->where('status', 'Em Andamento')->count();
        $concluidas = (clone $query)->where('status', 'Concluída')->count();

        $atrasadas = (clone $query)
            ->where('prazo_conclusao', '<', now())
            ->where('status', '!=', 'Concluída')
            ->where('status', '!=', 'Cancelada')
            ->count();

        $custoTotal = (clone $query)->sum('custo_estimado');

        return [
            Stat::make('OS por Status', "Abertas: {$abertas} | Em Andamento: {$emAndamento} | Concluídas: {$concluidas}")
                ->description('Resumo do status das ordens')
                ->descriptionIcon('heroicon-o-chart-pie')
                ->color('primary'),

            Stat::make('OS Atrasadas', $atrasadas)
                ->description('Atrasadas ou com prazo vencido')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($atrasadas > 0 ? 'danger' : 'success'),

            Stat::make('Custo Estimado Total', 'R$ '.number_format($custoTotal, 2, ',', '.'))
                ->description('Valor estimado para as OS filtradas')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('success'),
        ];
    }
}
