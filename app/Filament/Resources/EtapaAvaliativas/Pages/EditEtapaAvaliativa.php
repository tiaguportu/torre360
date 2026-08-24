<?php

namespace App\Filament\Resources\EtapaAvaliativas\Pages;

use App\Filament\Resources\EtapaAvaliativas\EtapaAvaliativaResource;
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
