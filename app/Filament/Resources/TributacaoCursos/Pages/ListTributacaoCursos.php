<?php

namespace App\Filament\Resources\TributacaoCursos\Pages;

use App\Filament\Resources\TributacaoCursos\TributacaoCursoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTributacaoCursos extends ListRecords
{
    protected static string $resource = TributacaoCursoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
