<?php

namespace App\Filament\Resources\TemplateContratos\Pages;

use App\Filament\Resources\TemplateContratos\TemplateContratoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTemplateContrato extends EditRecord
{
    protected static string $resource = TemplateContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
