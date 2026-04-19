<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Pages;

use App\Filament\Resources\AvaliacaoHabilidades\AvaliacaoHabilidadeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvaliacaoHabilidades extends ListRecords
{
    protected static string $resource = AvaliacaoHabilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
