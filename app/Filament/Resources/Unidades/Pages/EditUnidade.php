<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUnidade extends EditRecord
{
    protected static string $resource = UnidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
