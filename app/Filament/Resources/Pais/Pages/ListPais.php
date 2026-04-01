<?php

namespace App\Filament\Resources\Pais\Pages;

use App\Filament\Resources\Pais\PaisResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPais extends ListRecords
{
    protected static string $resource = PaisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
