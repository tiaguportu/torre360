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
            Action::make('comparar_relacionadas')
                ->label('Comparar Relacionadas')
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
        $html .= '<p>Esta página apresenta em detalhes as respostas fornecidas por um respondente a um questionário específico, além do histórico de reenvios relacionados.</p>';

        $html .= '<h4>Ações disponíveis nesta página:</h4><ul>';

        if ($user->can('Update:QuestionarioResposta')) {
            $html .= '<li><strong>Editar:</strong> Permite alterar metadados do envio da resposta (como status e datas).</li>';
        }

        $html .= '<li><strong>Respostas Relacionadas:</strong> Caso esta resposta seja um reenvio ou possua submissões filhas, você verá links diretos para navegar entre elas.</li>';
        $html .= '<li><strong>Comparar Relacionadas:</strong> Abre uma visualização comparativa lado a lado com todas as respostas vinculadas a esta (original e reenvios).</li>';

        $html .= '</ul>';

        return $html;
    }
}
