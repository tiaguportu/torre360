<?php

namespace App\Filament\Resources\DocumentoObrigatorios\Pages;

use App\Filament\Resources\DocumentoObrigatorios\DocumentoObrigatorioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentoObrigatorios extends ListRecords
{
    protected static string $resource = DocumentoObrigatorioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
