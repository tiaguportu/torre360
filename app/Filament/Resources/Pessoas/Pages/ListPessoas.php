<?php

namespace App\Filament\Resources\Pessoas\Pages;

use App\Filament\Imports\PessoaImporter;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Models\Pessoa;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListPessoas extends ListRecords
{
    protected static string $resource = PessoaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(PessoaImporter::class)
                ->visible(fn (): bool => auth()->user()->can('import', Pessoa::class)),
        ];
    }
}
