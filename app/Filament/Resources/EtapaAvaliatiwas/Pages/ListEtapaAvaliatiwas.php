<?php

namespace App\Filament\Resources\EtapaAvaliatiwas\Pages;

use App\Filament\Resources\EtapaAvaliatiwas\EtapaAvaliativaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEtapaAvaliatiwas extends ListRecords
{
    protected static string $resource = EtapaAvaliativaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
