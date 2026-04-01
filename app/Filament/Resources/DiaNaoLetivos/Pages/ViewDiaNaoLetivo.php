<?php

namespace App\Filament\Resources\DiaNaoLetivos\Pages;

use App\Filament\Resources\DiaNaoLetivos\DiaNaoLetivoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDiaNaoLetivo extends ViewRecord
{
    protected static string $resource = DiaNaoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
