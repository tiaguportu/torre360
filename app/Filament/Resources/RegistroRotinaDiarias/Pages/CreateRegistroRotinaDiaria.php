<?php

namespace App\Filament\Resources\RegistroRotinaDiarias\Pages;

use App\Filament\Resources\RegistroRotinaDiarias\RegistroRotinaDiariaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistroRotinaDiaria extends CreateRecord
{
    protected static string $resource = RegistroRotinaDiariaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registrado_por_user_id'] = auth()->id();

        return $data;
    }
}
