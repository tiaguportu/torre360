<?php

namespace App\Filament\Resources\Notas\Pages;

use App\Filament\Resources\Notas\NotaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNota extends ViewRecord
{
    protected static string $resource = NotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
