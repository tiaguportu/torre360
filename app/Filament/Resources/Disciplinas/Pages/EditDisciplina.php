<?php

namespace App\Filament\Resources\Disciplinas\Pages;

use App\Filament\Resources\Disciplinas\DisciplinaResource;
use App\Filament\Widgets\CronogramaCalendarWidget;
use App\Models\Disciplina;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditDisciplina extends EditRecord
{
    protected static string $resource = DisciplinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    /** @var Disciplina $disciplina */
                    $disciplina = $this->record;

                    if ($disciplina->possuiNotasVinculadas()) {
                        Notification::make()
                            ->danger()
                            ->title('Não é possível excluir')
                            ->body('Esta disciplina possui notas lançadas vinculadas. Remova as notas antes de excluir a disciplina.')
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    protected function beforeSave(): void
    {
        /** @var Disciplina $disciplina */
        $disciplina = $this->record;

        if ($disciplina->possuiNotasVinculadas()) {
            Notification::make()
                ->danger()
                ->title('Não é possível editar')
                ->body('Esta disciplina possui notas lançadas vinculadas. Não é permitido editar disciplinas com notas já lançadas.')
                ->persistent()
                ->send();

            throw new Halt();
        }
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
