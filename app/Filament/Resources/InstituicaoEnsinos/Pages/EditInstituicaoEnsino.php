<?php

namespace App\Filament\Resources\InstituicaoEnsinos\Pages;

use App\Filament\Resources\InstituicaoEnsinos\InstituicaoEnsinoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstituicaoEnsino extends EditRecord
{
    protected static string $resource = InstituicaoEnsinoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
