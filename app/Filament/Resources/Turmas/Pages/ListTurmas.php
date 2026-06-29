<?php

namespace App\Filament\Resources\Turmas\Pages;

use App\Filament\Resources\Turmas\TurmaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListTurmas extends ListRecords
{
    protected static string $resource = TurmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Turmas')
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

        $canCreate = $user->can('Create:Turma');
        $canUpdate = $user->can('Update:Turma');
        $canViewBoletim = $user->can('Boletim:Matricula');

        $html = '<p>Nesta página você gerencia as turmas da instituição, vinculando-as a séries e períodos letivos.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Listagem:</strong> Visualize todas as turmas ativas e seus respectivos cursos/séries.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Nova Turma:</strong> Crie uma nova turma definindo o nome, turno e capacidade.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Altere dados da turma ou encerre turmas antigas.</li>';
        }

        if ($canViewBoletim) {
            $html .= '<li><strong>Imprimir Boletins (tabela):</strong> Permite gerar e baixar em PDF os boletins de todos os alunos ativos de uma turma específica para a etapa selecionada.</li>';
            $html .= '<li><strong>Imprimir Boletins em Lote (lote):</strong> Permite selecionar várias turmas para gerar um único PDF com os boletins de todos os alunos ativos correspondentes.</li>';
        }

        $html .= '<li><strong>Vínculos:</strong> As turmas são essenciais para o processo de matrícula e lançamento de notas.</li>';
        $html .= '</ul>';

        return $html;
    }
}
