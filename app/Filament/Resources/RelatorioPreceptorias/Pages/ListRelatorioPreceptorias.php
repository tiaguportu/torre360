<?php

namespace App\Filament\Resources\RelatorioPreceptorias\Pages;

use App\Filament\Resources\RelatorioPreceptorias\RelatorioPreceptoriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRelatorioPreceptorias extends ListRecords
{
    protected static string $resource = RelatorioPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
