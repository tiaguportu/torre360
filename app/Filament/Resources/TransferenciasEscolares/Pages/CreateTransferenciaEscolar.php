<?php

namespace App\Filament\Resources\TransferenciasEscolares\Pages;

use App\Filament\Resources\TransferenciasEscolares\TransferenciaEscolarResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransferenciaEscolar extends CreateRecord
{
    protected static string $resource = TransferenciaEscolarResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();

        return $data;
    }
}
