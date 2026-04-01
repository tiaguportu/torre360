<?php

namespace App\Filament\Resources\DocumentoObrigatorios\Pages;

use App\Filament\Resources\DocumentoObrigatorios\DocumentoObrigatorioResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentoObrigatorio extends EditRecord
{
    protected static string $resource = DocumentoObrigatorioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
