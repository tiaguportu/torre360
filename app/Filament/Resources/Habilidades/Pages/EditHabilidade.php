<?php

namespace App\Filament\Resources\Habilidades\Pages;

use App\Filament\Resources\Habilidades\HabilidadeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHabilidade extends EditRecord
{
    protected static string $resource = HabilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
