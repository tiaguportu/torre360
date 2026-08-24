<?php

namespace App\Filament\Resources\EtapaAvaliativas\Pages;

use App\Filament\Resources\EtapaAvaliativas\EtapaAvaliativaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEtapaAvaliativas extends ListRecords
{
    protected static string $resource = EtapaAvaliativaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
