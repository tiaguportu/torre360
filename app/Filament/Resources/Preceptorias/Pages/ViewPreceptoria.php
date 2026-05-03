<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPreceptoria extends ViewRecord
{
    protected static string $resource = PreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
