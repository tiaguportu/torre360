<?php

namespace App\Filament\Resources\Alunos\Pages;

use App\Filament\Resources\Alunos\AlunoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListAlunos extends ListRecords
{
    protected static string $resource = AlunoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Alunos')
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

        $canCreate = $user->can('Create:Aluno');
        $canUpdate = $user->can('Update:Aluno');
        $canView = $user->can('View:Aluno');
        $canBoletim = $user->can('Boletim:Aluno');

        $html = '<p>Nesta página você gerencia o cadastro base dos alunos da instituição.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Visualizar:</strong> Veja a lista de todos os alunos cadastrados e utilize a busca para encontrar nomes específicos.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Aluno:</strong> Cadastre um novo aluno no sistema.</li>';
        }

        $html .= '<li><strong>Ações:</strong><ul>';
        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Atualize dados pessoais, documentos e vínculos do aluno.</li>';
        }
        if ($canBoletim) {
            $html .= '<li><strong>Boletim:</strong> Acesse o rendimento escolar atual do aluno.</li>';
        }
        if ($canView) {
            $html .= '<li><strong>Histórico:</strong> Visualize as matrículas e o histórico acadêmico vinculado.</li>';
        }
        $html .= '</ul></li>';
        $html .= '</ul>';

        return $html;
    }
}
