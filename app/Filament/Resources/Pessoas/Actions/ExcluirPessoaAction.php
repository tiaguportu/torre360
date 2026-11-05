<?php

namespace App\Filament\Resources\Pessoas\Actions;

use App\Models\Pessoa;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;

/**
 * Excluir pessoa só quando nada depende dela (`Pessoa::getInviabilityReasons()`: matrículas, contratos, CRM, vínculos
 * familiares, usuário de acesso...). Existe porque o banco apaga as matrículas de uma pessoa em cascata, e com elas o
 * histórico do aluno. Usada na lista e na edição da pessoa.
 */
class ExcluirPessoaAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->before(function (DeleteAction $action, Pessoa $record): void {
            $motivos = $record->getInviabilityReasons();

            if ($motivos === []) {
                return;
            }

            Notification::make()
                ->danger()
                ->title('Não é possível excluir esta pessoa')
                ->body('Existem vínculos ativos que impedem a exclusão: '.implode(', ', $motivos))
                ->persistent()
                ->send();

            $action->cancel();
        });
    }
}
