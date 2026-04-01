<?php

namespace App\Filament\Resources\Notas\Pages;

use App\Filament\Resources\Notas\NotaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotas extends ListRecords
{
    protected static string $resource = NotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
