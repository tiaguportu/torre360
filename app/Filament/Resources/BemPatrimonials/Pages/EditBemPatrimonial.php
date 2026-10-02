<?php

namespace App\Filament\Resources\BemPatrimonials\Pages;

use App\Filament\Resources\BemPatrimonials\BemPatrimonialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBemPatrimonial extends EditRecord
{
    protected static string $resource = BemPatrimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
