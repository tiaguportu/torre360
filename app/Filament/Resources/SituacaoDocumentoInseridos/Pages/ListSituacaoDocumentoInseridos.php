<?php

namespace App\Filament\Resources\SituacaoDocumentoInseridos\Pages;

use App\Filament\Resources\SituacaoDocumentoInseridos\SituacaoDocumentoInseridoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSituacaoDocumentoInseridos extends ListRecords
{
    protected static string $resource = SituacaoDocumentoInseridoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
