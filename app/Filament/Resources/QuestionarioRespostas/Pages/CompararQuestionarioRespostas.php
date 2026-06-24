<?php

namespace App\Filament\Resources\QuestionarioRespostas\Pages;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
use App\Models\QuestionarioResposta;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;

class CompararQuestionarioRespostas extends Page
{
    protected static string $resource = QuestionarioRespostaResource::class;

    protected string $view = 'filament.resources.questionario-respostas.comparacao';

    public Collection $records;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount()
    {
        $ids = request()->query('ids');
        if (empty($ids) || ! is_array($ids)) {
            abort(404, 'Nenhum questionário selecionado.');
        }

        $this->records = QuestionarioResposta::whereIn('id', $ids)->get();

        if ($this->records->isEmpty()) {
            abort(404, 'Nenhum questionário selecionado.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimir_pdf')
                ->label('Imprimir PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->url(fn (): string => route('questionario-respostas.comparar.pdf', ['ids' => request()->query('ids')]))
                ->openUrlInNewTab(),

            Action::make('ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda - Comparação de Respostas')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $content = "<div class='space-y-4'>";
        $content .= '<p>Esta página permite visualizar e comparar detalhadamente as respostas de múltiplos questionários selecionados na tabela.</p>';

        $content .= "<h3 class='font-bold'>Funcionalidades:</h3>";
        $content .= "<ul class='list-disc ml-4'>";
        $content .= '<li><strong>Imprimir PDF:</strong> Permite gerar e fazer o download de um arquivo PDF formatado contendo a tabela de comparação das respostas dos questionários selecionados.</li>';
        $content .= '</ul>';
        $content .= '</div>';

        return $content;
    }
}
