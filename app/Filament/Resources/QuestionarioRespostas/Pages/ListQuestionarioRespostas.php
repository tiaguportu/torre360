<?php

namespace App\Filament\Resources\QuestionarioRespostas\Pages;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListQuestionarioRespostas extends ListRecords
{
    protected static string $resource = QuestionarioRespostaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
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

        $html = '<h3>Ajuda - Respostas de Questionários</h3>';
        $html .= '<p>Esta página apresenta todas as respostas de questionários submetidas pelos usuários.</p>';

        $html .= '<h4>Ações disponíveis:</h4><ul>';

        if ($user->can('Create:QuestionarioResposta')) {
            $html .= '<li><strong>Criar Resposta:</strong> Permite registrar manualmente uma resposta de questionário no sistema.</li>';
        }

        $html .= '<li><strong>Busca e Filtros:</strong> Pesquise respostas pelo título do questionário, nome do respondente ou perfil institucional.</li>';

        $html .= '<li><strong>Ações de Linha:</strong>';
        $html .= '<ul>';
        if ($user->can('View:QuestionarioResposta')) {
            $html .= '<li><em>Visualizar:</em> Detalha a resposta preenchida na íntegra, exibindo também as respostas originais/filhas vinculadas.</li>';
        }
        $html .= '<li><em>Responder Novamente:</em> Inicia o preenchimento de uma nova resposta para o mesmo questionário, vinculando a resposta atual como origem (parent).</li>';
        $html .= '</ul>';
        $html .= '</li>';

        $html .= '<li><strong>Ações em Massa:</strong>';
        $html .= '<ul>';
        $html .= '<li><em>Comparar Respostas:</em> Selecione várias respostas e clique em Comparar Respostas para ver e baixar um comparativo detalhado.</li>';
        if ($user->can('Delete:QuestionarioResposta')) {
            $html .= '<li><em>Excluir:</em> Exclui as respostas selecionadas.</li>';
        }
        $html .= '</ul>';
        $html .= '</li>';

        $html .= '</ul>';

        return $html;
    }
}
