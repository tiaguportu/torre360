<?php

namespace App\Filament\Resources\DiaNaoLetivos\Pages;

use App\Filament\Resources\DiaNaoLetivos\DiaNaoLetivoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiaNaoLetivos extends ListRecords
{
    protected static string $resource = DiaNaoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
