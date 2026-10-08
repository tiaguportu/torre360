<?php

namespace App\Filament\Resources\RegistroRotinaDiarias\Pages;

use App\Filament\Resources\RegistroRotinaDiarias\RegistroRotinaDiariaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRegistroRotinaDiaria extends EditRecord
{
    protected static string $resource = RegistroRotinaDiariaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
