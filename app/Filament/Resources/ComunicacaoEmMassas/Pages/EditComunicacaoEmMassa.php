<?php

namespace App\Filament\Resources\ComunicacaoEmMassas\Pages;

use App\Filament\Resources\ComunicacaoEmMassas\ComunicacaoEmMassaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComunicacaoEmMassa extends EditRecord
{
    protected static string $resource = ComunicacaoEmMassaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
