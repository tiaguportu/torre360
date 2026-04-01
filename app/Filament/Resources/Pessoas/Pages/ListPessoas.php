<?php

namespace App\Filament\Resources\Pessoas\Pages;

use App\Filament\Resources\Pessoas\PessoaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPessoas extends ListRecords
{
    protected static string $resource = PessoaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
