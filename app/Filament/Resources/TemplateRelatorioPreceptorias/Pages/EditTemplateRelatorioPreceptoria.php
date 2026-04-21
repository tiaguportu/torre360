<?php

namespace App\Filament\Resources\TemplateRelatorioPreceptorias\Pages;

use App\Filament\Resources\TemplateRelatorioPreceptorias\TemplateRelatorioPreceptoriaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTemplateRelatorioPreceptoria extends EditRecord
{
    protected static string $resource = TemplateRelatorioPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
