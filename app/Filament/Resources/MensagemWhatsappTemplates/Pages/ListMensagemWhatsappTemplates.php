<?php

namespace App\Filament\Resources\MensagemWhatsappTemplates\Pages;

use App\Filament\Resources\MensagemWhatsappTemplates\MensagemWhatsappTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMensagemWhatsappTemplates extends ListRecords
{
    protected static string $resource = MensagemWhatsappTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
