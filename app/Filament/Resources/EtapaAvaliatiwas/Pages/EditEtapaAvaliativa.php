<?php

namespace App\Filament\Resources\EtapaAvaliatiwas\Pages;

use App\Filament\Resources\EtapaAvaliatiwas\EtapaAvaliativaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEtapaAvaliativa extends EditRecord
{
    protected static string $resource = EtapaAvaliativaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
