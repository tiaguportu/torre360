<?php

namespace App\Filament\Resources\CorRacas\Pages;

use App\Filament\Resources\CorRacas\CorRacaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCorRaca extends EditRecord
{
    protected static string $resource = CorRacaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
