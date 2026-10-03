<?php

namespace App\Filament\Resources\MaterialAulas\Pages;

use App\Filament\Resources\MaterialAulas\MaterialAulaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaterialAula extends EditRecord
{
    protected static string $resource = MaterialAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
