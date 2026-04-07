<?php

namespace App\Filament\Resources\TipoVinculos\Pages;

use App\Filament\Resources\TipoVinculos\TipoVinculoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTipoVinculos extends ManageRecords
{
    protected static string $resource = TipoVinculoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
