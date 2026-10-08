<?php

namespace App\Filament\Resources\TipoConsentimentos\Pages;

use App\Filament\Resources\TipoConsentimentos\TipoConsentimentoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTipoConsentimento extends EditRecord
{
    protected static string $resource = TipoConsentimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
