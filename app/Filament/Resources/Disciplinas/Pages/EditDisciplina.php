<?php

namespace App\Filament\Resources\Disciplinas\Pages;

use App\Filament\Resources\Disciplinas\DisciplinaResource;
use App\Filament\Widgets\CronogramaCalendarWidget;
use App\Models\Disciplina;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
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
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Disciplina')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
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

            throw new Halt;
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

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Nesta tela você pode revisar e atualizar os dados do registro selecionado de <strong>Disciplina</strong>.</p>';
        $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Ações disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';

        if ($user && $user->can('Update:Disciplina')) {
            $html .= '<li><strong>Salvar alterações:</strong> Grava no sistema as modificações feitas nos campos do formulário.</li>';
        }

        if ($user && $user->can('Delete:Disciplina')) {
            $html .= '<li><strong>Excluir:</strong> Permite remover este registro caso não possua vínculos dependentes.</li>';
        }

        if ($user && $user->can('View:Disciplina')) {
            $html .= '<li><strong>Visualizar:</strong> Exibe a ficha completa com os dados salvos deste registro.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
