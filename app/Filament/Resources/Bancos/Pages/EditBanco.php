<?php

namespace App\Filament\Resources\Bancos\Pages;

use App\Filament\Resources\Bancos\BancoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBanco extends EditRecord
{
    protected static string $resource = BancoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
