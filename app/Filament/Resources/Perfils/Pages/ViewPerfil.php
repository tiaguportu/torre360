<?php

namespace App\Filament\Resources\Perfils\Pages;

use App\Filament\Resources\Perfils\PerfilResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerfil extends ViewRecord
{
    protected static string $resource = PerfilResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
