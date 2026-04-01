<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnidades extends ListRecords
{
    protected static string $resource = UnidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
