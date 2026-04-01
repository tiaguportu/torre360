<?php

namespace App\Filament\Resources\CorRacas\Pages;

use App\Filament\Resources\CorRacas\CorRacaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorRacas extends ListRecords
{
    protected static string $resource = CorRacaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
