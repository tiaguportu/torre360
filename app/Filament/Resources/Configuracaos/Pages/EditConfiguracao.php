<?php

namespace App\Filament\Resources\Configuracaos\Pages;

use App\Filament\Resources\Configuracaos\ConfiguracaoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConfiguracao extends EditRecord
{
    protected static string $resource = ConfiguracaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
