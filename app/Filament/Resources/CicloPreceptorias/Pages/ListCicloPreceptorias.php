<?php

namespace App\Filament\Resources\CicloPreceptorias\Pages;

use App\Filament\Resources\CicloPreceptorias\CicloPreceptoriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCicloPreceptorias extends ListRecords
{
    protected static string $resource = CicloPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
