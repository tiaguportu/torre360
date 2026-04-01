<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAvaliacao extends EditRecord
{
    protected static string $resource = AvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
