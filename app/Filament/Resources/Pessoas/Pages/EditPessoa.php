<?php

namespace App\Filament\Resources\Pessoas\Pages;

use App\Filament\Resources\Pessoas\PessoaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPessoa extends EditRecord
{
    protected static string $resource = PessoaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
