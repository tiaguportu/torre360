<?php

namespace App\Filament\Resources\Questionarios\Pages;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditQuestionario extends EditRecord
{
    protected static string $resource = QuestionarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            Action::make('ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $html = '<div class="space-y-4">';
        $html .= '<p>Nesta página, você pode configurar a estrutura do questionário, incluindo blocos temáticos e perguntas.</p>';

        $html .= '<h3 class="text-lg font-bold">Funcionalidades:</h3>';
        $html .= '<ul class="list-disc ml-6">';
        $html .= '<li><strong>Drag-and-Drop:</strong> Arraste os blocos ou perguntas para reordená-los.</li>';
        $html .= '<li><strong>Colapso:</strong> Clique no cabeçalho de um bloco ou pergunta para expandir ou recolher o conteúdo.</li>';

        if ($user->can('Update:Questionario')) {
            $html .= '<li>Você tem permissão para editar todas as informações do questionário.</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
