<?php

namespace App\Filament\Resources\EventosEscolares\Pages;

use App\Filament\Resources\EventosEscolares\EventoEscolarResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateEventoEscolar extends CreateRecord
{
    protected static string $resource = EventoEscolarResource::class;

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
            ->modalHeading('Ajuda: Criar Evento Escolar')
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
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Preencha os dados do evento escolar para disponibilizá-lo no calendário das famílias no Portal.</p>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Público Alvo:</strong> Escolha entre convidar toda a instituição ou apenas turmas selecionadas.</li>';
        $html .= '<li><strong>Autorização de Saída:</strong> Ative esta opção em passeios culturais para recolher o consentimento formal dos pais com registro de IP.</li>';
        $html .= '<li><strong>Limite de Vagas:</strong> Se preenchido, o sistema trava novas confirmações quando atingir a lotação máxima.</li>';
        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
