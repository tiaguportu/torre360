<?php

namespace App\Filament\Resources\Disciplinas\Pages;

use App\Filament\Resources\Disciplinas\DisciplinaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDisciplina extends EditRecord
{
    protected static string $resource = DisciplinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\CronogramaCalendarWidget::make([
                'fixedDisciplinaId' => $this->record->id,
            ]),
        ];
    }
}
