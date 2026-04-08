<?php

namespace App\Filament\Resources\CentroCustos\Pages;

use App\Filament\Resources\CentroCustos\CentroCustoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCentroCusto extends EditRecord
{
    protected static string $resource = CentroCustoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
