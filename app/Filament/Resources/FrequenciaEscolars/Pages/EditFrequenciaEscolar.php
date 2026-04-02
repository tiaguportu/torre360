<?php

namespace App\Filament\Resources\FrequenciaEscolars\Pages;

use App\Filament\Resources\FrequenciaEscolars\FrequenciaEscolarResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFrequenciaEscolar extends EditRecord
{
    protected static string $resource = FrequenciaEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
