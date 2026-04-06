<?php

namespace App\Filament\Resources\OrdemServicoResource\Widgets;

use App\Filament\Resources\OrdemServicoResource\Pages\ListOrdemServicos;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrdemServicoStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListOrdemServicos::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();

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
