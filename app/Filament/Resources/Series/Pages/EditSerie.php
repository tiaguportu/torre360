<?php

namespace App\Filament\Resources\Series\Pages;

use App\Filament\Resources\Series\SerieResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSerie extends EditRecord
{
    protected static string $resource = SerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
