<?php

namespace App\Filament\Resources\TransferenciasEscolares\Pages;

use App\Filament\Resources\TransferenciasEscolares\TransferenciaEscolarResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransferenciaEscolar extends EditRecord
{
    protected static string $resource = TransferenciaEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
