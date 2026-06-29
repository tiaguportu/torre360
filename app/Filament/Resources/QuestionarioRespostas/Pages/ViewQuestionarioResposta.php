<?php

namespace App\Filament\Resources\QuestionarioRespostas\Pages;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ViewRecord;

class ViewQuestionarioResposta extends ViewRecord
{
    protected static string $resource = QuestionarioRespostaResource::class;

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

        $html = '<h3>Ajuda - Visualização de Resposta</h3>';
        $html .= '<p>Esta página apresenta em detalhes as respostas fornecidas por um respondente a um questionário específico, além do histórico de reenvios relacionados.</p>';

        $html .= '<h4>Ações disponíveis nesta página:</h4><ul>';

        if ($user->can('Update:QuestionarioResposta')) {
            $html .= '<li><strong>Editar:</strong> Permite alterar metadados do envio da resposta (como status e datas).</li>';
        }

        $html .= '<li><strong>Respostas Relacionadas:</strong> Caso esta resposta seja um reenvio ou possua submissões filhas, você verá links diretos para navegar entre elas.</li>';

        $html .= '</ul>';

        return $html;
    }
}
