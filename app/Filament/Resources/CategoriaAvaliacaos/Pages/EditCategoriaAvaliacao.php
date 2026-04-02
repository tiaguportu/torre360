<?php

namespace App\Filament\Resources\CategoriaAvaliacaos\Pages;

use App\Filament\Resources\CategoriaAvaliacaos\CategoriaAvaliacaoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoriaAvaliacao extends EditRecord
{
    protected static string $resource = CategoriaAvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
