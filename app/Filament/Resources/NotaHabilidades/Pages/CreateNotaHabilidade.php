<?php

namespace App\Filament\Resources\NotaHabilidades\Pages;

use App\Filament\Resources\NotaHabilidades\NotaHabilidadeResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateNotaHabilidade extends CreateRecord
{
    protected static string $resource = NotaHabilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Lançar Nota de Habilidade')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>Esta página serve para lançar a nota de habilidade (conceito) de um aluno.</p>';
        $html .= '<h3>Instruções:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Avaliação de Habilidade:</strong> Selecione a avaliação correspondente.</li>';
        $html .= '<li><strong>Aluno:</strong> Selecione o aluno (filtrado de acordo com a turma vinculada à avaliação).</li>';
        $html .= '<li><strong>Conceito e Observação:</strong> Selecione o conceito obtido e adicione observações, se necessário.</li>';
        $html .= '</ul>';

        return $html;
    }
}
