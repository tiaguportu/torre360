<?php

namespace App\Filament\Resources\RelatorioPreceptorias\Pages;

use App\Filament\Resources\RelatorioPreceptorias\RelatorioPreceptoriaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRelatorioPreceptoria extends EditRecord
{
    protected static string $resource = RelatorioPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
