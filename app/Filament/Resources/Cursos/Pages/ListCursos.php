<?php

namespace App\Filament\Resources\Cursos\Pages;

use App\Filament\Resources\Cursos\CursoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListCursos extends ListRecords
{
    protected static string $resource = CursoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Cursos')
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
        
        $canCreate = $user->can('Create:Curso');
        $canUpdate = $user->can('Update:Curso');

        $html = '<p>Nesta página você gerencia os cursos oferecidos pela instituição.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Listagem:</strong> Visualize todos os cursos, seus nomes internos e externos.</li>';
        
        if ($canCreate) {
            $html .= '<li><strong>Novo Curso:</strong> Cadastre um novo curso no sistema.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Altere descrições, nomes e configurações gerais do curso.</li>';
        }

        $html .= '<li><strong>Estrutura:</strong> Os cursos são a base para a criação de Séries e Turmas.</li>';
        $html .= '</ul>';

        return $html;
    }
}
