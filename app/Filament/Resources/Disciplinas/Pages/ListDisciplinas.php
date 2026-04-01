<?php

namespace App\Filament\Resources\Disciplinas\Pages;

use App\Filament\Resources\Disciplinas\DisciplinaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDisciplinas extends ListRecords
{
    protected static string $resource = DisciplinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
