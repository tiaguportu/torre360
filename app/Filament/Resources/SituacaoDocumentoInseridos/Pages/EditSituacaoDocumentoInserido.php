<?php

namespace App\Filament\Resources\SituacaoDocumentoInseridos\Pages;

use App\Filament\Resources\SituacaoDocumentoInseridos\SituacaoDocumentoInseridoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSituacaoDocumentoInserido extends EditRecord
{
    protected static string $resource = SituacaoDocumentoInseridoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
