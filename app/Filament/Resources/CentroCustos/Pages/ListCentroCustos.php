<?php

namespace App\Filament\Resources\CentroCustos\Pages;

use App\Filament\Resources\CentroCustos\CentroCustoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCentroCustos extends ListRecords
{
    protected static string $resource = CentroCustoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
