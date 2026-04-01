<?php

namespace App\Filament\Resources\DocumentoInseridos\Pages;

use App\Filament\Resources\DocumentoInseridos\DocumentoInseridoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentoInseridos extends ListRecords
{
    protected static string $resource = DocumentoInseridoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
