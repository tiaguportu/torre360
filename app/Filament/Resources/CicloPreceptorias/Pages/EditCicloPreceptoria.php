<?php

namespace App\Filament\Resources\CicloPreceptorias\Pages;

use App\Filament\Resources\CicloPreceptorias\CicloPreceptoriaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCicloPreceptoria extends EditRecord
{
    protected static string $resource = CicloPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
