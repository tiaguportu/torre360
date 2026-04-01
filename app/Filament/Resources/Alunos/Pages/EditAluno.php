<?php

namespace App\Filament\Resources\Alunos\Pages;

use App\Filament\Resources\Alunos\AlunoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAluno extends EditRecord
{
    protected static string $resource = AlunoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
