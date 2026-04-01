<?php

namespace App\Filament\Resources\Coordenadors\Pages;

use App\Filament\Resources\Coordenadors\CoordenadorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoordenador extends EditRecord
{
    protected static string $resource = CoordenadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
