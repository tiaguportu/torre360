<?php

namespace App\Filament\Resources\InstituicaoEnsinos\Pages;

use App\Filament\Resources\InstituicaoEnsinos\InstituicaoEnsinoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstituicaoEnsinos extends ListRecords
{
    protected static string $resource = InstituicaoEnsinoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
