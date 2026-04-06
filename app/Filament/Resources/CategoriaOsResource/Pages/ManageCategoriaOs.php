<?php

namespace App\Filament\Resources\CategoriaOsResource\Pages;

use App\Filament\Resources\CategoriaOsResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCategoriaOs extends ManageRecords
{
    protected static string $resource = CategoriaOsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
