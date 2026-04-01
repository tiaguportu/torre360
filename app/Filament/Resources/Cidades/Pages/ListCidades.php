<?php

namespace App\Filament\Resources\Cidades\Pages;

use App\Filament\Resources\Cidades\CidadeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCidades extends ListRecords
{
    protected static string $resource = CidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
