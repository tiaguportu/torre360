<?php

namespace App\Filament\Resources\Salas\Pages;

use App\Filament\Resources\Salas\SalaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalas extends ListRecords
{
    protected static string $resource = SalaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
