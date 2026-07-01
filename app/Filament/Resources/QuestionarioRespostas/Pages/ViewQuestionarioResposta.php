<?php

namespace App\Filament\Resources\QuestionarioRespostas\Pages;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewQuestionarioResposta extends ViewRecord
{
    protected static string $resource = QuestionarioRespostaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('adicionar_feedback')
                ->label('Adicionar Feedback')
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->color('primary')
                ->form([
                    Textarea::make('texto')
                        ->label('Conteúdo do Feedback')
                        ->required()
                        ->rows(5)
                        ->placeholder('Escreva aqui o seu feedback avaliativo, observações ou pareceres...'),
                ])
                ->action(function ($record, array $data) {
                    $record->feedbacks()->create([
                        'user_id' => auth()->id(),
                        'texto' => $data['texto'],
                    ]);

                    Notification::make()
                        ->title('Feedback registrado com sucesso!')
                        ->success()
                        ->send();
                })
                ->visible(fn () => auth()->user()?->can('Create:QuestionarioResposta')),
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
        $html .= '<p>Esta página apresenta em detalhes as respostas fornecidas por um respondente a um questionário específico, além do histórico de feedbacks avaliativos.</p>';

        $html .= '<h4>Ações disponíveis nesta página:</h4><ul>';

        if ($user->can('Update:QuestionarioResposta')) {
            $html .= '<li><strong>Editar:</strong> Permite alterar metadados do envio da resposta (como status e datas).</li>';
        }

        if ($user->can('Create:QuestionarioResposta')) {
            $html .= '<li><strong>Adicionar Feedback:</strong> Abre um formulário em modal para registrar observações, comentários ou pareceres avaliativos sobre esta resposta.</li>';
        }

        $html .= '<li><strong>Feedbacks Registrados:</strong> Caso existam feedbacks vinculados a este envio, você os visualizará listados cronologicamente na seção inferior.</li>';

        $html .= '</ul>';

        return $html;
    }
}
