<?php

namespace App\Filament\Resources\Configuracaos\Pages;

use App\Filament\Resources\Configuracaos\ConfiguracaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConfiguracaos extends ListRecords
{
    protected static string $resource = ConfiguracaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
