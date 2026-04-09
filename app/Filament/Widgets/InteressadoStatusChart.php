<?php

namespace App\Filament\Widgets;

use App\Models\Interessado;
use Filament\Widgets\ChartWidget;

class InteressadoStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Interessados por Status';

    protected function getData(): array
    {
        $data = Interessado::query()
            ->join('status_interessado', 'interessado.status_interessado_id', '=', 'status_interessado.id')
            ->selectRaw('status_interessado.nome, count(*) as total, status_interessado.ordem')
            ->groupBy('status_interessado.nome', 'status_interessado.ordem')
            ->orderBy('status_interessado.ordem')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => 'rgba(153, 102, 255, 0.6)',
                ],
            ],
            'labels' => $data->pluck('nome')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
