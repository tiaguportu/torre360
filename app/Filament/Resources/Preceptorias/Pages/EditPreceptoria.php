<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditPreceptoria extends EditRecord
{
    protected static string $resource = PreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Preceptoria')
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
        $html = '<p>Nesta página você pode editar os dados de uma preceptoria cadastrada.</p>';
        $html .= '<h3>Orientações:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Data e Horários:</strong> Altere a data, hora de início e hora de término conforme necessário.</li>';
        $html .= '<li><strong>Professor e Aluno:</strong> É possível redefinir o professor responsável ou a matrícula do aluno vinculado.</li>';
        $html .= '<li><strong>Exclusão:</strong> Utilize o botão "Excluir" no topo da página caso deseje remover este atendimento.</li>';
        $html .= '</ul>';

        return $html;
    }
}
