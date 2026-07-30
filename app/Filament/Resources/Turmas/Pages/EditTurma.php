<?php

namespace App\Filament\Resources\Turmas\Pages;

use App\Filament\Resources\Turmas\TurmaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditTurma extends EditRecord
{
    protected static string $resource = TurmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Turma')
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

        $canDelete = $user->can('Delete:Turma');

        $html = '<p>Nesta página você pode editar as informações da turma selecionada.</p>';
        $html .= '<h3>Recursos e Ações:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Dados da Turma:</strong> Atualize o nome, código, série, turno, vagas, tipo de mediação didático-pedagógica, tipo de turma, local diferenciado e a flag de Educação Especial.</li>';

        if ($canDelete) {
            $html .= '<li><strong>Excluir:</strong> Permite remover a turma caso não existam dependências que impeçam a exclusão.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
