<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Models\Avaliacao;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAvaliacao extends EditRecord
{
    protected static string $resource = AvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Avaliacao $record */
        $record = $this->getRecord();
        $isHomologada = $record->isHomologada();

        return [
            Action::make('homologar')
                ->label('Homologar Fechamento')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->visible(fn (): bool => ! $this->getRecord()->isHomologada() && (auth()->user()?->can('homologar', $this->getRecord()) ?? false))
                ->requiresConfirmation()
                ->modalHeading('Homologar Fechamento da Avaliação')
                ->modalDescription('Ao homologar a avaliação, o lançamento de notas será encerrado e bloqueado contra alterações. Deseja continuar?')
                ->modalSubmitActionLabel('Sim, homologar fechamento')
                ->action(function () {
                    /** @var Avaliacao $record */
                    $record = $this->getRecord();
                    $record->homologar(auth()->user());

                    Notification::make()
                        ->title('Avaliação homologada com sucesso!')
                        ->body('O fechamento foi registrado e o lançamento de notas está bloqueado.')
                        ->success()
                        ->send();

                    $this->fillForm();
                }),

            Action::make('desbloquear')
                ->label('Desbloquear Avaliação')
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->isHomologada() && (auth()->user()?->can('homologar', $this->getRecord()) ?? false))
                ->requiresConfirmation()
                ->modalHeading('Desbloquear Avaliação')
                ->modalDescription('Deseja reabrir o lançamento de notas e edição desta avaliação?')
                ->modalSubmitActionLabel('Sim, desbloquear')
                ->action(function () {
                    /** @var Avaliacao $record */
                    $record = $this->getRecord();
                    $record->desbloquear();

                    Notification::make()
                        ->title('Avaliação desbloqueada com sucesso!')
                        ->body('A avaliação está liberada novamente para edições.')
                        ->success()
                        ->send();

                    $this->fillForm();
                }),

            DeleteAction::make()
                ->disabled($isHomologada)
                ->tooltip($isHomologada ? 'Avaliações homologadas não podem ser excluídas.' : null),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Avaliação')
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

    private function getHelpContent(): string
    {
        $user = auth()->user();
        /** @var Avaliacao $record */
        $record = $this->getRecord();

        $canUpdate = $user->can('Update:Avaliacao');
        $canDelete = $user->can('Delete:Avaliacao');
        $canHomologar = $user->can('homologar', $record);

        $html = '<p>Nesta página você pode visualizar ou alterar os detalhes de uma avaliação já cadastrada no sistema.</p>';

        if ($record->isHomologada()) {
            $html .= '<p class="text-warning-600 font-semibold">🔒 Esta avaliação encontra-se homologada. O lançamento de notas está bloqueado e exclusões não são permitidas enquanto estiver trancada.</p>';
        }

        $html .= '<h3>Ações Disponíveis</h3>';
        $html .= '<ul>';

        if ($canUpdate) {
            $html .= '<li><strong>Salvar Alterações:</strong> Modifique os campos desejados (ex: descrição, data ou peso) e clique em "Salvar alterações" no rodapé.</li>';
        }

        if ($canHomologar) {
            $html .= '<li><strong>Homologar / Desbloquear:</strong> Use os botões no topo para trancar o fechamento da avaliação ou reabrir para ajustes quando necessário.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Excluir:</strong> Use o botão "Excluir" no topo da página para remover esta avaliação (disponível apenas quando não homologada).</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
