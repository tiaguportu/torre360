<?php

namespace App\Filament\Resources\Sexos\Pages;

use App\Filament\Resources\Sexos\SexoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSexos extends ListRecords
{
    protected static string $resource = SexoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
