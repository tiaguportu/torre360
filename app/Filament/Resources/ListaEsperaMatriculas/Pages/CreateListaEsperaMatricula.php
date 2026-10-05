<?php

namespace App\Filament\Resources\ListaEsperaMatriculas\Pages;

use App\Filament\Resources\ListaEsperaMatriculas\ListaEsperaMatriculaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateListaEsperaMatricula extends CreateRecord
{
    protected static string $resource = ListaEsperaMatriculaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();

        return $data;
    }
}
