<?php

namespace App\Filament\Resources\Disciplinas\Pages;

use App\Filament\Resources\Disciplinas\DisciplinaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListDisciplinas extends ListRecords
{
    protected static string $resource = DisciplinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Disciplinas')
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
        
        $canCreate = $user->can('Create:Disciplina');
        $canUpdate = $user->can('Update:Disciplina');

        $html = '<p>Aqui você gerencia a grade curricular (disciplinas) da instituição.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Catálogo:</strong> Visualize todas as disciplinas cadastradas e suas áreas de conhecimento.</li>';
        
        if ($canCreate) {
            $html .= '<li><strong>Nova Disciplina:</strong> Cadastre uma nova disciplina (ex: Matemática, Português).</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Atualize nomes, siglas ou categorias.</li>';
        }

        $html .= '<li><strong>Base Acadêmica:</strong> As disciplinas são necessárias para a montagem de horários e lançamento de avaliações.</li>';
        $html .= '</ul>';

        return $html;
    }
}
