<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Pages;

use App\Filament\Resources\MensagemWhatsappTemplates\MensagemWhatsappTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMensagemWhatsappTemplate extends EditRecord
{
    protected static string $resource = MensagemWhatsappTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
