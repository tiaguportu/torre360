<?php

namespace App\Filament\Resources\Habilidades\Pages;

use App\Filament\Resources\Habilidades\HabilidadeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHabilidades extends ListRecords
{
    protected static string $resource = HabilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
