<?php

namespace App\Filament\Resources\DocumentoInseridos\Pages;

use App\Filament\Resources\DocumentoInseridos\DocumentoInseridoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentoInserido extends ViewRecord
{
    protected static string $resource = DocumentoInseridoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
