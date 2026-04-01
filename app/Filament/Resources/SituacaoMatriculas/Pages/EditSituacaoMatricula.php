<?php

namespace App\Filament\Resources\SituacaoMatriculas\Pages;

use App\Filament\Resources\SituacaoMatriculas\SituacaoMatriculaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSituacaoMatricula extends EditRecord
{
    protected static string $resource = SituacaoMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
