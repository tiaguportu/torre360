<?php

namespace App\Filament\Resources\Pais\Pages;

use App\Filament\Resources\Pais\PaisResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPais extends EditRecord
{
    protected static string $resource = PaisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
