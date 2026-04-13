<?php

namespace App\Filament\Resources\Disciplinas\Pages;

use App\Filament\Resources\Disciplinas\DisciplinaResource;
use App\Filament\Widgets\CronogramaCalendarWidget;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDisciplina extends EditRecord
{
    protected static string $resource = DisciplinaResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        \Log::info('EditDisciplina - Dados ao carregar:', $data);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        \Log::info('EditDisciplina - Dados antes de salvar:', $data);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CronogramaCalendarWidget::make([
                'fixedDisciplinaId' => $this->record->id,
            ]),
        ];
    }
}
