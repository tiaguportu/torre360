<?php

namespace App\Filament\Resources\ComunicacaoEmMassas\Pages;

use App\Filament\Resources\ComunicacaoEmMassas\ComunicacaoEmMassaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComunicacaoEmMassas extends ListRecords
{
    protected static string $resource = ComunicacaoEmMassaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
