<?php

namespace App\Filament\Resources\Cidades\Pages;

use App\Filament\Resources\Cidades\CidadeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCidade extends EditRecord
{
    protected static string $resource = CidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
