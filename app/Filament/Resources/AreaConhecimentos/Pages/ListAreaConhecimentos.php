<?php

namespace App\Filament\Resources\AreaConhecimentos\Pages;

use App\Filament\Resources\AreaConhecimentos\AreaConhecimentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAreaConhecimentos extends ListRecords
{
    protected static string $resource = AreaConhecimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
