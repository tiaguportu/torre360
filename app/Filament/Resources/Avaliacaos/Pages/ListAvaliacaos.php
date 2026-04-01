<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvaliacaos extends ListRecords
{
    protected static string $resource = AvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
