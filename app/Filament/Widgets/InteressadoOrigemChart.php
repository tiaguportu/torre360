<?php

namespace App\Filament\Widgets;

use App\Models\Interessado;
use Filament\Widgets\ChartWidget;

class InteressadoOrigemChart extends ChartWidget
{
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
