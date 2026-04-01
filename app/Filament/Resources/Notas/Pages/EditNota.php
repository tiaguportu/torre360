<?php

namespace App\Filament\Resources\Notas\Pages;

use App\Filament\Resources\Notas\NotaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNota extends EditRecord
{
    protected static string $resource = NotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
