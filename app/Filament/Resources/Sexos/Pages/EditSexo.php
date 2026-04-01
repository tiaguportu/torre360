<?php

namespace App\Filament\Resources\Sexos\Pages;

use App\Filament\Resources\Sexos\SexoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSexo extends EditRecord
{
    protected static string $resource = SexoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
