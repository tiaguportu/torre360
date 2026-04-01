<?php

namespace App\Filament\Resources\Perfils\Pages;

use App\Filament\Resources\Perfils\PerfilResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerfil extends EditRecord
{
    protected static string $resource = PerfilResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
