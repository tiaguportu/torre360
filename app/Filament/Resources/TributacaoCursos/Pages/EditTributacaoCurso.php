<?php

namespace App\Filament\Resources\TributacaoCursos\Pages;

use App\Filament\Resources\TributacaoCursos\TributacaoCursoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTributacaoCurso extends EditRecord
{
    protected static string $resource = TributacaoCursoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
