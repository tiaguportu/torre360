<?php

namespace App\Filament\Resources\CorRacas\Pages;

use App\Filament\Resources\CorRacas\CorRacaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCorRaca extends ViewRecord
{
    protected static string $resource = CorRacaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
