<?php

namespace App\Filament\Resources\EventosEscolares\Pages;

use App\Filament\Resources\EventosEscolares\EventoEscolarResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListEventosEscolares extends ListRecords
{
    protected static string $resource = EventoEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->getHelpHeaderAction(),
        ];
    }

    protected function getHelpHeaderAction(): Action
    {
        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: Eventos Escolares e RSVP')
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
        $user = auth()->user();
        $canCreate = $user->can('Create:EventoEscolar');
        $canEdit = $user->can('Update:EventoEscolar');

        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Esta tela permite organizar e publicar <strong>Eventos e Atividades Escolares</strong> (Reuniões de Pais, Passeios, Festas e Feiras), acompanhando as confirmações de presença (RSVP) e termos de autorização assinados pelas famílias.</p>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Funcionalidades Disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';

        if ($canCreate) {
            $html .= '<li><strong>Cadastrar Novo Evento:</strong> Permite criar eventos definindo data/hora, público convidado e se exige autorização digital.</li>';
        }

        $html .= '<li><strong>Lista de Presença:</strong> Visualize em tempo real os responsáveis que confirmaram presença e os acompanhantes declarados.</li>';

        if ($canEdit) {
            $html .= '<li><strong>Editar Evento:</strong> Atualize a programação, capacidade de vagas ou prazos de confirmação.</li>';
        }

        $html .= '<li><strong>Filtros Avançados:</strong> Localize eventos por categoria ou situação (ativos/inativos).</li>';
        $html .= '</ul>';

        $html .= '</div>';

        return $html;
    }
}
