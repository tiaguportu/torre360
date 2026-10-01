<?php

namespace App\Filament\Resources\CentroCustos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CentroCustos\CentroCustoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCentroCustos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CentroCustoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🧾', 'Centros de Custo', 'Agrupadores usados para classificar despesas e receitas.', 'CentroCusto',
                'Veja o nome e se o centro de custo está ativo.', 'Cadastre um novo centro de custo.', 'Altere o nome ou desative o centro de custo.',
                dica: 'Centros de custo podem ser vinculados às transações bancárias.'),
        ];
    }
}
