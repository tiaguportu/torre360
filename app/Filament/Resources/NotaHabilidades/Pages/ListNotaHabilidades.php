<?php

namespace App\Filament\Resources\NotaHabilidades\Pages;

use App\Filament\Resources\NotaHabilidades\NotaHabilidadeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListNotaHabilidades extends ListRecords
{
    protected static string $resource = NotaHabilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Notas de Habilidades')
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
        $html = '<p>Esta página apresenta a lista de Notas de Habilidades lançadas para os alunos.</p>';
        $html .= '<h3>Funcionalidades:</h3>';
        $html .= '<ul>';
        if ($user->can('Create:NotaHabilidade')) {
            $html .= '<li><strong>Lançar Nota:</strong> Permite cadastrar o conceito obtido por um aluno em uma avaliação de habilidade específica.</li>';
        }
        if ($user->can('Update:NotaHabilidade')) {
            $html .= '<li><strong>Editar:</strong> Permite modificar o conceito e as observações de uma nota lançada.</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
