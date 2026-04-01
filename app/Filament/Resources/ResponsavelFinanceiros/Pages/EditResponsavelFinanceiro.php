<?php

namespace App\Filament\Resources\ResponsavelFinanceiros\Pages;

use App\Filament\Resources\ResponsavelFinanceiros\ResponsavelFinanceiroResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResponsavelFinanceiro extends EditRecord
{
    protected static string $resource = ResponsavelFinanceiroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
