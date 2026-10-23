<?php

namespace App\Filament\Widgets;

use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Traits\HasCustomWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    use HasCustomWidgetShield;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $counts = Cache::remember('widget_stats_overview_counts', 60, function () {
            return [
                'total_pessoas' => Pessoa::count(),
                'matriculas_ativas' => Matricula::count(),
                'turmas_ativas' => Turma::ativas()->count(),
            ];
        });

        return [
            Stat::make('Total de Pessoas', $counts['total_pessoas'])
                ->description('Total de pessoas cadastradas')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
            Stat::make('Matrículas Ativas', $counts['matriculas_ativas'])
                ->description('Dentro do período letivo atual')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('Turmas Ativas', $counts['turmas_ativas'])
                ->description('Turmas com status Ativa')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),
        ];
    }
}
