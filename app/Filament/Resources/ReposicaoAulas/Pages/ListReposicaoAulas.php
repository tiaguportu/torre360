<?php

namespace App\Filament\Resources\ReposicaoAulas\Pages;

use App\Filament\Resources\ReposicaoAulas\ReposicaoAulaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListReposicaoAulas extends ListRecords
{
    protected static string $resource = ReposicaoAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Reposição de Aulas e Carga Letiva')
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
        $activeRole = session('active_role');

        $canCreate = $user ? $user->can('Create:ReposicaoAula') : false;
        $canDelete = $user ? $user->can('Delete:ReposicaoAula') : false;

        $html = '<p>Esta tela permite gerenciar o agendamento e a execução de <strong>reposições de aulas</strong> para cumprimento da carga letiva mínima anual exigida pela Lei de Diretrizes e Bases da Educação Nacional (LDB nº 9.394/1996, art. 24, I — 800 horas no Fundamental e 1.000 horas no Médio, distribuídas em 200 dias de efetivo trabalho escolar).</p>';
        $html .= '<h3>Funcionalidades disponíveis:</h3><ul>';
        $html .= '<li><strong>Listagem e Filtros:</strong> Visualize as aulas de reposição programadas e filtre por turma, disciplina ou situação (Agendada, Realizada, Cancelada).</li>';

        if ($canCreate) {
            $html .= '<li><strong>Nova Reposição:</strong> Utilize o botão <em>"Novo Reposição de Aula"</em> para agendar uma data de reposição, definindo turma, disciplina e horário.</li>';
        }

        $html .= '<li><strong>Concluir Reposição:</strong> Clique na ação <em>"Concluir"</em> na linha da reposição assim que a aula for ministrada para somar as horas no realizado.</li>';

        if ($canDelete) {
            $html .= '<li><strong>Exclusão:</strong> Registros agendados indevidamente podem ser excluídos.</li>';
        }

        $html .= '</ul>';

        if ($activeRole) {
            $html .= "<p class='text-xs text-gray-500 mt-2'>Papel ativo atual: <strong>{$activeRole}</strong></p>";
        }

        return $html;
    }
}
