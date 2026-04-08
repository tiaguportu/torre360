<?php

namespace App\Filament\Resources\CodigoBacens\Pages;

use App\Filament\Resources\CodigoBacens\CodigoBacenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCodigoBacen extends EditRecord
{
    protected static string $resource = CodigoBacenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
