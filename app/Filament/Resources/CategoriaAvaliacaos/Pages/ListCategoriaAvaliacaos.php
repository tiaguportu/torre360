<?php

namespace App\Filament\Resources\CategoriaAvaliacaos\Pages;

use App\Filament\Resources\CategoriaAvaliacaos\CategoriaAvaliacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoriaAvaliacaos extends ListRecords
{
    protected static string $resource = CategoriaAvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
