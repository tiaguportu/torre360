<?php

namespace App\Filament\Resources\CampanhaMarketings\Pages;

use App\Filament\Resources\CampanhaMarketings\CampanhaMarketingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCampanhaMarketing extends EditRecord
{
    protected static string $resource = CampanhaMarketingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
