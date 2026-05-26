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

        $canUpdate = $user->can('Update:Turma');
        $canDelete = $user->can('Delete:Turma');

        $html = '<p>Nesta página você pode visualizar ou alterar os detalhes de uma turma e gerenciar a sua grade de disciplinas, alunos matriculados e habilidades a serem avaliadas.</p>';
        $html .= '<h3>Ações Disponíveis</h3>';
        $html .= '<ul>';

        if ($canUpdate) {
            $html .= '<li><strong>Salvar Alterações:</strong> Modifique os campos da turma (ex: nome, período letivo, turno, professor conselheiro) e clique em "Salvar alterações" no rodapé.</li>';
            $html .= '<li><strong>Gerenciar Grade Curricular (Disciplinas):</strong> Na aba de Disciplinas, você pode vincular uma disciplina existente ou criar uma nova, definindo qual professor é o responsável por ministrá-la nesta turma.</li>';
            $html .= '<li><strong>Gerenciar Matrículas e Habilidades:</strong> Utilize as respectivas abas na parte inferior da página para matricular alunos e associar competências (habilidades) a serem avaliadas.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Excluir:</strong> Use o botão "Excluir" no topo da página para remover esta turma do sistema. Atenção: Isso não será permitido se houver matrículas ativas ou dados acadêmicos vinculados.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
