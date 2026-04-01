<?php

namespace App\Filament\Widgets;

use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        return [
            Stat::make('Total de Alunos', Pessoa::count())
                ->description('Total de pessoas cadastradas')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
            Stat::make('Matrículas Ativas', Matricula::count())
                ->description('Dentro do período letivo atual')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('Turmas Ativas', Turma::count())
                ->description('Contagem por período')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),
        ];
    }
}
