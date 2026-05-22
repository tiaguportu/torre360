<?php

namespace App\Filament\Widgets;

use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Role;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    public static function canView(): bool
    {
        $activeRole = session('active_role');

        if (! $activeRole) {
            return false;
        }

        if ($activeRole === 'super_admin') {
            return true;
        }

        try {
            $role = Role::findByName($activeRole, 'web');

            return $role->hasPermissionTo(static::getPermissionName());
        } catch (\Exception $e) {
            return false;
        }
    }

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        return [
            Stat::make('Total de Pessoas', Pessoa::count())
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
