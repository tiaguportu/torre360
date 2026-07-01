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
            Action::make('comparar_feedbacks')
                ->label('Comparar Feedbacks')
                ->icon('heroicon-o-arrows-right-left')
                ->color('success')
                ->url(function ($record) {
                    $ids = collect([$record->id])
                        ->merge($record->parent_id ? [$record->parent_id] : [])
                        ->merge($record->children()->pluck('id'))
                        ->unique()
                        ->toArray();

                    return QuestionarioRespostaResource::getUrl('comparar', ['ids' => $ids]);
                })
                ->visible(fn ($record) => $record->parent_id !== null || $record->children()->exists()),
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
        $html .= '<p>Esta página apresenta em detalhes as respostas fornecidas por um respondente a um questionário específico, além do histórico de feedbacks vinculados.</p>';

        $html .= '<h4>Ações disponíveis nesta página:</h4><ul>';

        if ($user->can('Update:QuestionarioResposta')) {
            $html .= '<li><strong>Editar:</strong> Permite alterar metadados do envio da resposta (como status e datas).</li>';
        }

        $html .= '<li><strong>Feedbacks Relacionados:</strong> Caso esta resposta possua feedbacks vinculados (seja um feedback de origem ou reenvios posteriores), você verá os links diretos para visualização e navegação na seção inferior.</li>';
        $html .= '<li><strong>Comparar Feedbacks:</strong> Permite comparar lado a lado de forma rápida a resposta original e todas as respostas de feedbacks enviadas posteriormente.</li>';

        $html .= '</ul>';

        return $html;
    }
}
