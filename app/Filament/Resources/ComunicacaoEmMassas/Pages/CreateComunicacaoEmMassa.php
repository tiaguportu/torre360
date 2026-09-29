<?php

namespace App\Filament\Resources\ComunicacaoEmMassas\Pages;

use App\Filament\Resources\ComunicacaoEmMassas\ComunicacaoEmMassaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateComunicacaoEmMassa extends CreateRecord
{
    protected static string $resource = ComunicacaoEmMassaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['enviado_por_user_id'] = auth()->id();

        return $data;
    }
}
