<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cardapios\Pages;

use App\Filament\Resources\Cardapios\CardapioResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCardapio extends CreateRecord
{
    protected static string $resource = CardapioResource::class;

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
