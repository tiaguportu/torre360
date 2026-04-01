<?php

namespace App\Filament\Resources\PeriodoLetivos\Pages;

use App\Filament\Resources\PeriodoLetivos\PeriodoLetivoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPeriodoLetivo extends EditRecord
{
    protected static string $resource = PeriodoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
