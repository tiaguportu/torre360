<?php

namespace App\Filament\Resources\DiaNaoLetivos\Pages;

use App\Filament\Resources\DiaNaoLetivos\DiaNaoLetivoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDiaNaoLetivo extends EditRecord
{
    protected static string $resource = DiaNaoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
