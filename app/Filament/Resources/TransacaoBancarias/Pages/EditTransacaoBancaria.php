<?php

namespace App\Filament\Resources\TransacaoBancarias\Pages;

use App\Filament\Resources\TransacaoBancarias\TransacaoBancariaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransacaoBancaria extends EditRecord
{
    protected static string $resource = TransacaoBancariaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
