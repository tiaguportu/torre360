<?php

namespace App\Filament\Resources\PlanoContas\Pages;

use App\Filament\Resources\PlanoContas\PlanoContaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanoConta extends EditRecord
{
    protected static string $resource = PlanoContaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
