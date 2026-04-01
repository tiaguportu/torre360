<?php

namespace App\Filament\Resources\AreaConhecimentos\Pages;

use App\Filament\Resources\AreaConhecimentos\AreaConhecimentoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAreaConhecimento extends EditRecord
{
    protected static string $resource = AreaConhecimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
