<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListAvaliacaos extends ListRecords
{
    protected static string $resource = AvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Avaliações')
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
        
        $canCreate = $user->can('Create:Avaliacao');
        $canUpdate = $user->can('Update:Avaliacao');
        $canLancarNotas = $user->can('LancarNotas:Avaliacao');

        $html = '<p>Nesta página você gerencia as avaliações (provas, trabalhos, testes) vinculadas às turmas e disciplinas.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Calendário de Provas:</strong> Visualize todas as avaliações agendadas, seus pesos e datas.</li>';
        
        if ($canCreate) {
            $html .= '<li><strong>Nova Avaliação:</strong> Cadastre uma nova atividade avaliativa definindo a etapa (bimestre/trimestre), o tipo e a pontuação máxima.</li>';
        }

        if ($canLancarNotas) {
            $html .= '<li><strong>Lançar Notas:</strong> Utilize a ação de tabela para abrir a planilha de lançamento de notas dos alunos.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Altere datas ou critérios de avaliação antes de lançar as notas.</li>';
        }

        $html .= '<li><strong>Importante:</strong> As avaliações criadas aqui compõem a média final do aluno no boletim.</li>';
        $html .= '</ul>';

        return $html;
    }
}
