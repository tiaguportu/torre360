<?php

namespace App\Filament\Resources\PlanoAulas\Pages;

use App\Filament\Resources\PlanoAulas\PlanoAulaResource;
use App\Models\PlanoAula;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPlanoAula extends EditRecord
{
    protected static string $resource = PlanoAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        /** @var PlanoAula $plano */
        $plano = $this->record;

        if ($plano->foiExecutado()) {
            Notification::make()
                ->danger()
                ->title('Não é possível editar')
                ->body('Este plano de aula já foi executado e virou um registro no diário de aulas.')
                ->persistent()
                ->send();

            throw new Halt;
        }
    }
}
