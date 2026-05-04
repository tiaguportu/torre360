<?php

namespace App\Filament\Resources\Series\Pages;

use App\Filament\Resources\Series\SerieResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListSeries extends ListRecords
{
    protected static string $resource = SerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Séries')
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
        
        $canCreate = $user->can('Create:Serie');
        $canUpdate = $user->can('Update:Serie');

        $html = '<p>Nesta página você organiza as séries que compõem cada curso.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Organização:</strong> Visualize as séries vinculadas a cada curso (ex: 1º Ano, 2º Ano).</li>';
        
        if ($canCreate) {
            $html .= '<li><strong>Nova Série:</strong> Adicione uma nova etapa/série a um curso existente.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Altere a ordem, nome ou curso vinculado.</li>';
        }

        $html .= '<li><strong>Importante:</strong> A correta configuração das séries permite o enturramento adequado dos alunos.</li>';
        $html .= '</ul>';

        return $html;
    }
}
