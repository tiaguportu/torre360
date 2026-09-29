<?php

namespace App\Filament\Resources\CampanhaMarketings\Pages;

use App\Filament\Resources\CampanhaMarketings\CampanhaMarketingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCampanhaMarketings extends ListRecords
{
    protected static string $resource = CampanhaMarketingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
