<?php

namespace App\Filament\Resources\Salas\Pages;

use App\Filament\Resources\Salas\SalaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSala extends EditRecord
{
    protected static string $resource = SalaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
