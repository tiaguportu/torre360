<?php

namespace App\Filament\Widgets;

use App\Models\Interessado;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Spatie\Permission\Models\Role;

class InteressadoOrigemChart extends ChartWidget
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

    protected static ?int $sort = 4;

    protected ?string $heading = 'Origem dos Interessados';

    protected function getData(): array
    {
        $data = Interessado::query()
            ->join('origem_interessado', 'interessado.origem_interessado_id', '=', 'origem_interessado.id')
            ->selectRaw('origem_interessado.nome, count(*) as total')
            ->groupBy('origem_interessado.nome')
            ->pluck('total', 'nome');

        return [
            'datasets' => [
                [
                    'label' => 'Quantidade',
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                    ],
                ],
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
