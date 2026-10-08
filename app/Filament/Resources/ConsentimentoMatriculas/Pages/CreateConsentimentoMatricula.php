<?php

namespace App\Filament\Resources\ConsentimentoMatriculas\Pages;

use App\Enums\StatusConsentimento;
use App\Filament\Resources\ConsentimentoMatriculas\ConsentimentoMatriculaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConsentimentoMatricula extends CreateRecord
{
    protected static string $resource = ConsentimentoMatriculaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['status'] ?? null) !== StatusConsentimento::Pendente->value) {
            $data['respondido_em'] = now();
            $data['respondido_por_user_id'] = auth()->id();
        }

        return $data;
    }
}
