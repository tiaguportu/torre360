<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoManutencaos\Pages;

use App\Filament\Resources\PlanoManutencaos\PlanoManutencaoResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlanoManutencao extends CreateRecord
{
    protected static string $resource = PlanoManutencaoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
