<?php

namespace App\Filament\Resources\TipoBolsas\Pages;

use App\Filament\Resources\TipoBolsas\TipoBolsaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTipoBolsa extends EditRecord
{
    protected static string $resource = TipoBolsaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
