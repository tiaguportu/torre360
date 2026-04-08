<?php

namespace App\Filament\Resources\CodigoBacens\Pages;

use App\Filament\Resources\CodigoBacens\CodigoBacenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCodigoBacens extends ListRecords
{
    protected static string $resource = CodigoBacenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
