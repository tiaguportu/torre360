<?php

namespace App\Filament\Resources\Matriculas\Actions;

use App\Models\Matricula;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;

/**
 * Excluir matrícula sem perder histórico: o modal diz o que impede a exclusão e, ao confirmar, nada é apagado.
 * Usada em todas as telas que excluem uma matrícula (edição, relação da pessoa e relação da turma); a regra em si
 * está em `Matricula::historicoQueImpedeExclusao()` e no gancho `deleting` do modelo.
 */
class ExcluirMatriculaAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->modalDescription(fn (Matricula $record): ?string => $record->possuiHistorico()
            ? 'Esta matrícula tem histórico ('.$record->descreverHistorico($record->historicoQueImpedeExclusao()).') e não pode ser excluída. Para encerrá-la sem perder o histórico, use o Cancelamento de Matrícula.'
            : null);

        $this->before(function (DeleteAction $action, Matricula $record): void {
            $historico = $record->historicoQueImpedeExclusao();

            if ($historico === []) {
                return;
            }

            Notification::make()
                ->title('Matrícula não pode ser excluída')
                ->body('Ela tem histórico ('.$record->descreverHistorico($historico).'). Use o Cancelamento de Matrícula para encerrá-la sem perder esse histórico.')
                ->danger()
                ->send();

            $action->cancel();
        });
    }
}
