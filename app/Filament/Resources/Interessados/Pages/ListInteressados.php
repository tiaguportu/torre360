<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\Actions\ImportarLeadIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Filament\Widgets\CrmFollowUpCalendarWidget;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListInteressados extends ListRecords
{
    protected static string $resource = InteressadoResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            CrmFollowUpCalendarWidget::make(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ImportarLeadIaAction::make(),
            Action::make('kanban')
                ->label('Ver Kanban')
                ->icon('heroicon-o-view-columns')
                ->color('info')
                ->url(InteressadoResource::getUrl('kanban')),
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: CRM (Interessados)')
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

        $canCreate = $user->can('Create:Interessado');
        $canUpdate = $user->can('Update:Interessado');

        $html = '<p>Nesta página você faz a gestão dos contatos interessados na escola (prospecção).</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Prospecção:</strong> Visualize a lista de pessoas que entraram em contato demonstrando interesse.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Interessado:</strong> Registre um novo contato vindo do site ou presencial.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Seguimento:</strong> Atualize o status do interessado (ex: Agendou visita, Matriculado, Desistiu).</li>';
        }

        $html .= '<li><strong>Histórico:</strong> Registre as interações e observações de cada contato para não perder o fio da meada.</li>';
        $html .= '</ul>';

        return $html;
    }
}
