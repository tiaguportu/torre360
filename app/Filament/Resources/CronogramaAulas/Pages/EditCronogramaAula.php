<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCronogramaAula extends EditRecord
{
    protected static string $resource = CronogramaAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
