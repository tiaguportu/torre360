<?php

namespace App\Filament\Resources\Titulos\Pages;

use App\Filament\Resources\Titulos\TituloResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTitulos extends ListRecords
{
    protected static string $resource = TituloResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
