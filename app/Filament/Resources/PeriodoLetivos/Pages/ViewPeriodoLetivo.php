<?php

namespace App\Filament\Resources\PeriodoLetivos\Pages;

use App\Filament\Resources\PeriodoLetivos\PeriodoLetivoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPeriodoLetivo extends ViewRecord
{
    protected static string $resource = PeriodoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
