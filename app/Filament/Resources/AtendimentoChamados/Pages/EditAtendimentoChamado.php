<?php

namespace App\Filament\Resources\AtendimentoChamados\Pages;

use App\Filament\Resources\AtendimentoChamados\AtendimentoChamadoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditAtendimentoChamado extends EditRecord
{
    protected static string $resource = AtendimentoChamadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->getHelpHeaderAction(),
        ];
    }

    protected function getHelpHeaderAction(): Action
    {
        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: Detalhes do Chamado')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->form([
                ViewField::make('help_content')
                    ->view('filament.components.help-content')
                    ->viewData(['content' => $this->getHelpContent()]),
            ]);
    }

    private function getHelpContent(): string
    {
        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Aqui você pode alterar a situação do chamado, reatribuir o atendente responsável e auditar o histórico de mensagens trocadas.</p>';
        $html .= '</div>';

        return $html;
    }
}
