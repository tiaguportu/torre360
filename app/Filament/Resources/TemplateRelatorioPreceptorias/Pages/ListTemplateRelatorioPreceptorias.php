<?php

namespace App\Filament\Resources\TemplateRelatorioPreceptorias\Pages;

use App\Filament\Resources\TemplateRelatorioPreceptorias\TemplateRelatorioPreceptoriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTemplateRelatorioPreceptorias extends ListRecords
{
    protected static string $resource = TemplateRelatorioPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
