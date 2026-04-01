<?php

namespace App\Filament\Resources\Sexos\Pages;

use App\Filament\Resources\Sexos\SexoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSexo extends ViewRecord
{
    protected static string $resource = SexoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
