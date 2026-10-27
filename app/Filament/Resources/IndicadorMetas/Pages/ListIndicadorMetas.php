<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicadorMetas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\IndicadorMetas\IndicadorMetaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndicadorMetas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = IndicadorMetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova meta'),
            $this->ajudaAction('Metas dos indicadores', HelpContent::make('🚩', 'Metas dos indicadores', 'A meta do ano dá cor ao cartão do Painel da Direção.')
                ->passos('🔁 Como usar', [
                    'Escolha o indicador, o ano e a meta no mesmo formato do cartão (número, reais ou percentual).',
                    'A tolerância é a faixa amarela: quanto o valor pode ficar pior que a meta antes de ficar vermelho.',
                    'Para indicadores que existem por unidade, cadastre a meta da unidade; sem ela vale a da escola toda.',
                ])
                ->secao('📌 Bom saber', [
                    ['🟢', 'Verde', 'Meta atingida (ou melhor).'],
                    ['🟡', 'Amarelo', 'Pior que a meta, mas dentro da tolerância.'],
                    ['🔴', 'Vermelho', 'Passou da tolerância.'],
                    ['↕️', 'Maior ou menor é melhor', 'Cada indicador sabe o sentido: para matrículas, mais é melhor; para inadimplência, menos é melhor.'],
                ])),
        ];
    }
}
