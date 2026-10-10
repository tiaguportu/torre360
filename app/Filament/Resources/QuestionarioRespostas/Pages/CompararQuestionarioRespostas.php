<?php

namespace App\Filament\Resources\QuestionarioRespostas\Pages;

use App\Filament\Resources\QuestionarioRespostas\QuestionarioRespostaResource;
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

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user && ($user->can('ViewAny:QuestionarioResposta') || $user->hasRole('super_admin'));
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $ids = request()->query('ids');
        if (empty($ids) || ! is_array($ids)) {
            abort(404, 'Nenhum questionário selecionado.');
        }

        $idsLimpos = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($idsLimpos)) {
            abort(404, 'Nenhum questionário selecionado.');
        }

        // Carrega estritamente através do escopo autorizado do Resource (Filament Shield + ownership)
        $this->records = QuestionarioRespostaResource::getEloquentQuery()
            ->whereIn('id', $idsLimpos)
            ->get();

        // Se algum dos questionários solicitados não pertencer ao escopo autorizado do usuário, bloqueia acesso (anti-IDOR)
        if ($this->records->count() !== count($idsLimpos)) {
            abort(403, 'Acesso não autorizado a um ou mais questionários selecionados.');
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
