<?php

namespace App\Filament\Resources\PlanoContas\Pages;

use App\Filament\Resources\PlanoContas\PlanoContaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanoContas extends ListRecords
{
    protected static string $resource = PlanoContaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
