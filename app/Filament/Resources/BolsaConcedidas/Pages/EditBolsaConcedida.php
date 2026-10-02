<?php

namespace App\Filament\Resources\BolsaConcedidas\Pages;

use App\Filament\Resources\BolsaConcedidas\BolsaConcedidaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBolsaConcedida extends EditRecord
{
    protected static string $resource = BolsaConcedidaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
