<?php

namespace App\Filament\Widgets;

use App\Models\Turma;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class AlunosPorTurmaChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Alunos por Turma';

    protected function getData(): array
    {
        $data = Turma::withCount('matriculas')->get();

        return [
            'datasets' => [
                [
                    'label' => 'Alunos',
                    'data' => $data->pluck('matriculas_count')->toArray(),
                    'backgroundColor' => [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316',
                    ],
                ],
            ],
            'labels' => $data->pluck('nome')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
