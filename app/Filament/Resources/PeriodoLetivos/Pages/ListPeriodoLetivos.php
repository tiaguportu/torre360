<?php

namespace App\Filament\Resources\PeriodoLetivos\Pages;

use App\Filament\Resources\PeriodoLetivos\PeriodoLetivoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPeriodoLetivos extends ListRecords
{
    protected static string $resource = PeriodoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
