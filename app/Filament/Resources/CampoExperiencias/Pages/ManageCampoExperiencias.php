<?php

namespace App\Filament\Resources\CampoExperiencias\Pages;

use App\Filament\Resources\CampoExperiencias\CampoExperienciaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCampoExperiencias extends ManageRecords
{
    protected static string $resource = CampoExperienciaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
