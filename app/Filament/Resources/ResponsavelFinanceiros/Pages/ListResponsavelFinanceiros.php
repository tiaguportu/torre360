<?php

namespace App\Filament\Resources\ResponsavelFinanceiros\Pages;

use App\Filament\Resources\ResponsavelFinanceiros\ResponsavelFinanceiroResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResponsavelFinanceiros extends ListRecords
{
    protected static string $resource = ResponsavelFinanceiroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
