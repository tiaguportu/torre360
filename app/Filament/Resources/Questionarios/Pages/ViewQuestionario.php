<?php

namespace App\Filament\Resources\Questionarios\Pages;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use App\Filament\Resources\Questionarios\Widgets\QuestionarioStats;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ViewRecord;

class ViewQuestionario extends ViewRecord
{
    protected static string $resource = QuestionarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<h3>Ajuda - Visualização de Questionário</h3>';
        $html .= '<p>Esta página exibe os detalhes do questionário, incluindo sua estrutura de blocos e perguntas, seu público-alvo e as permissões definidas.</p>';

        $html .= '<h4>Informações Visíveis:</h4><ul>';
        $html .= '<li><strong>Geral:</strong> Mostra as configurações básicas como título, período de aplicação e se o questionário é ativo ou anônimo.</li>';
        $html .= '<li><strong>Público-Alvo:</strong> Lista quais unidades, cursos, séries, turmas ou usuários podem responder a este questionário.</li>';
        $html .= '<li><strong>Permissões:</strong> Mostra os donos e observadores configurados.</li>';
        $html .= '<li><strong>Estrutura:</strong> Permite ver todos os blocos de perguntas cadastrados.</li>';
        $html .= '</ul>';

        if ($user->can('Update:Questionario')) {
            $html .= '<p><strong>Edição:</strong> Se você deseja modificar a estrutura ou configurações deste questionário, clique no botão <strong>Editar</strong> no topo da página.</p>';
        }

        return $html;
    }

    protected function getFooterWidgets(): array
    {
        return [
            QuestionarioStats::class,
        ];
    }
}
