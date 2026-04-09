<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\InteressadoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInteressado extends EditRecord
{
    protected static string $resource = InteressadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
