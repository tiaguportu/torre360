<?php

namespace App\Filament\Resources\DocumentoInseridos\Pages;

use App\Filament\Resources\DocumentoInseridos\DocumentoInseridoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentoInserido extends EditRecord
{
    protected static string $resource = DocumentoInseridoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
