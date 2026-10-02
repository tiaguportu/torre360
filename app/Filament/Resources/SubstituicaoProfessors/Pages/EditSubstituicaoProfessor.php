<?php

namespace App\Filament\Resources\SubstituicaoProfessors\Pages;

use App\Filament\Resources\SubstituicaoProfessors\SubstituicaoProfessorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubstituicaoProfessor extends EditRecord
{
    protected static string $resource = SubstituicaoProfessorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
