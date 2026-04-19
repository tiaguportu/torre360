<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Pages;

use App\Filament\Resources\AvaliacaoHabilidades\AvaliacaoHabilidadeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAvaliacaoHabilidade extends EditRecord
{
    protected static string $resource = AvaliacaoHabilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
