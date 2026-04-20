<?php

namespace App\Filament\Resources\CategoriaAvaliacaos\Pages;

use App\Filament\Resources\CategoriaAvaliacaos\CategoriaAvaliacaoResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategoriaAvaliacao extends EditRecord
{
    protected static string $resource = CategoriaAvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    if ($this->record->avaliacaos()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Não é possível excluir')
                            ->body('Esta categoria possui avaliações vinculadas. Remova as avaliações antes de excluir a categoria.')
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
