<?php

namespace App\Filament\Resources\SituacaoMatriculas\Pages;

use App\Filament\Resources\SituacaoMatriculas\SituacaoMatriculaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSituacaoMatriculas extends ListRecords
{
    protected static string $resource = SituacaoMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
