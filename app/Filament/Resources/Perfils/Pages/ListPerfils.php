<?php

namespace App\Filament\Resources\Perfils\Pages;

use App\Filament\Resources\Perfils\PerfilResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerfils extends ListRecords
{
    protected static string $resource = PerfilResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
