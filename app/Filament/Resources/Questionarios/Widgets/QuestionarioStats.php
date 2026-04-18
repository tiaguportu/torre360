<?php

namespace App\Filament\Resources\Questionarios\Widgets;

use App\Models\Questionario;
use App\Models\QuestionarioResposta;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class QuestionarioStats extends ChartWidget
{
    protected ?string $heading = 'Respostas por Questionário';

    public ?Questionario $record = null;

    protected function getData(): array
    {
        $query = QuestionarioResposta::query();

        if ($this->record) {
            $query->where('questionario_id', $this->record->id);
        }

        $data = $query->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total de Respostas',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => [
                        '#fbbf24', // pendente
                        '#10b981', // enviado
                    ],
                ],
            ],
            'labels' => $data->pluck('status')->map(fn ($s) => ucfirst($s))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
