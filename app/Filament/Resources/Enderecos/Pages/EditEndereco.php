<?php

namespace App\Filament\Resources\Enderecos\Pages;

use App\Filament\Resources\Enderecos\EnderecoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEndereco extends EditRecord
{
    protected static string $resource = EnderecoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
