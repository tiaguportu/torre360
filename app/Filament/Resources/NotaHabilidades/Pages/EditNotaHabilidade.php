<?php

namespace App\Filament\Resources\NotaHabilidades\Pages;

use App\Filament\Resources\NotaHabilidades\NotaHabilidadeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditNotaHabilidade extends EditRecord
{
    protected static string $resource = NotaHabilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Nota de Habilidade')
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
        $html = '<p>Esta página permite editar o lançamento da nota de habilidade de um aluno.</p>';
        $html .= '<h3>Opções:</h3>';
        $html .= '<ul>';
        $html .= '<li>Modifique o conceito ou a observação nos campos fornecidos.</li>';
        if ($user->can('Delete:NotaHabilidade')) {
            $html .= '<li><strong>Excluir:</strong> Exclui permanentemente este lançamento.</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
