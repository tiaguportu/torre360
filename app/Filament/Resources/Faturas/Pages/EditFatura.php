<?php

namespace App\Filament\Resources\Faturas\Pages;

use App\Filament\Resources\Faturas\FaturaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFatura extends EditRecord
{
    protected static string $resource = FaturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
