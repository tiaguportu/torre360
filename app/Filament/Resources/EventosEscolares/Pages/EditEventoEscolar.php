<?php

namespace App\Filament\Resources\EventosEscolares\Pages;

use App\Filament\Resources\EventosEscolares\EventoEscolarResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditEventoEscolar extends EditRecord
{
    protected static string $resource = EventoEscolarResource::class;

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
            ->modalHeading('Ajuda: Editar Evento Escolar')
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
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Atualize as informações do evento escolar selecionado.</p>';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Caso o prazo de confirmação seja estendido, as famílias que ainda não responderam voltarão a ver o alerta de RSVP pendente no Portal.</p>';
        $html .= '</div>';

        return $html;
    }
}
