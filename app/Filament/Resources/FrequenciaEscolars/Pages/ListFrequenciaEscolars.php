<?php

namespace App\Filament\Resources\FrequenciaEscolars\Pages;

use App\Filament\Resources\FrequenciaEscolars\FrequenciaEscolarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFrequenciaEscolars extends ListRecords
{
    protected static string $resource = FrequenciaEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
