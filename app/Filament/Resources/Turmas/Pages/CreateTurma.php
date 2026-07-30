<?php

namespace App\Filament\Resources\Turmas\Pages;

use App\Filament\Resources\Turmas\TurmaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateTurma extends CreateRecord
{
    protected static string $resource = TurmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Turma')
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
        $user = auth()->user();

        $html = '<p>Nesta página você pode cadastrar uma nova Turma no sistema.</p>';
        $html .= '<h3>Campos Disponíveis:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Nome:</strong> Identificação principal da turma.</li>';
        $html .= '<li><strong>Código:</strong> Código identificador da turma (ex: censo/INEP ou interno).</li>';
        $html .= '<li><strong>Série e Turno:</strong> Vínculo com a série escolar e o turno de funcionamento.</li>';
        $html .= '<li><strong>Tipo de mediação didático-pedagógica:</strong> 1-Presencial, 2-Semipresencial, 3-Educação a distância (EAD).</li>';
        $html .= '<li><strong>Tipo de turma:</strong> 4-Atividade complementar, 5-AEE, 6-Curricular, 9-Curricular com Atividade Complementar.</li>';
        $html .= '<li><strong>Local de funcionamento diferenciado:</strong> 0-Não diferenciado, 1-Sala anexa, 2-Unidade socioeducativa, 3-Unidade prisional.</li>';
        $html .= '<li><strong>Turma de Educação Especial:</strong> Flag indicando se é uma classe especial.</li>';
        $html .= '</ul>';

        return $html;
    }
}
