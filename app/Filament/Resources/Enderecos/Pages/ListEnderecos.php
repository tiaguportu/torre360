<?php

namespace App\Filament\Resources\Enderecos\Pages;

use App\Filament\Resources\Enderecos\EnderecoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnderecos extends ListRecords
{
    protected static string $resource = EnderecoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
