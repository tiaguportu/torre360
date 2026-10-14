<?php

namespace App\Filament\Resources\ListaEsperaMatriculas\Pages;

use App\Filament\Resources\ListaEsperaMatriculas\ListaEsperaMatriculaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditListaEsperaMatricula extends EditRecord
{
    protected static string $resource = ListaEsperaMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
