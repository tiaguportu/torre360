<?php

namespace App\Filament\Resources\AtendimentoChamados\Pages;

use App\Filament\Resources\AtendimentoChamados\AtendimentoChamadoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateAtendimentoChamado extends CreateRecord
{
    protected static string $resource = AtendimentoChamadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getHelpHeaderAction(),
        ];
    }

    protected function getHelpHeaderAction(): Action
    {
        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: Abertura de Chamado')
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
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Cadastre um novo chamado de atendimento para acompanhamento oficial da solicitação.</p>';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">O número de protocolo será gerado automaticamente pelo sistema após salvar.</p>';
        $html .= '</div>';

        return $html;
    }
}
