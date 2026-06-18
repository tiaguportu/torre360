<?php

namespace App\Filament\Resources\Questionarios\Pages;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListQuestionarios extends ListRecords
{
    protected static string $resource = QuestionarioResource::class;

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

        $html = '<h3>Ajuda - Listagem de Questionários</h3>';
        $html .= '<p>Esta página apresenta todos os questionários cadastrados e seu status de aplicação.</p>';

        $html .= '<h4>Ações disponíveis:</h4><ul>';

        if ($user->can('Create:Questionario')) {
            $html .= '<li><strong>Novo Questionário:</strong> Permite criar um novo questionário com blocos e perguntas personalizados.</li>';
        }

        $html .= '<li><strong>Filtros e Busca:</strong> Use o campo de busca para pesquisar questionários pelo título.</li>';

        $html .= '<li><strong>Ações de Linha:</strong>';
        $html .= '<ul>';
        $html .= '<li><em>Responder:</em> Permite preencher o questionário caso você seja um respondente elegível.</li>';
        if ($user->can('View:Questionario')) {
            $html .= '<li><em>Visualizar:</em> Exibe os detalhes e a estrutura do questionário.</li>';
        }
        if ($user->can('Update:Questionario')) {
            $html .= '<li><em>Editar:</em> Permite alterar os dados gerais, estrutura e perguntas do questionário.</li>';
            $html .= '<li><em>Avisar Respondedores:</em> Envia um lembrete por e-mail para todos os possíveis respondedores do questionário.</li>';
        }
        $html .= '</ul>';
        $html .= '</li>';

        $html .= '</ul>';

        return $html;
    }
}
