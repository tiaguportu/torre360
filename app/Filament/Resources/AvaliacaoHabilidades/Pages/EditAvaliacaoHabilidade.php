<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Pages;

use App\Filament\Resources\AvaliacaoHabilidades\AvaliacaoHabilidadeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditAvaliacaoHabilidade extends EditRecord
{
    protected static string $resource = AvaliacaoHabilidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Avaliação de Habilidade')
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
        $html = '<p>Esta página permite editar as informações gerais do cabeçalho de uma Avaliação de Habilidade.</p>';
        $html .= '<h3>Opções:</h3>';
        $html .= '<ul>';
        $html .= '<li>Modifique os dados nos campos fornecidos e salve a alteração.</li>';
        if ($user->can('Delete:AvaliacaoHabilidade')) {
            $html .= '<li><strong>Excluir:</strong> Exclui permanentemente este registro de avaliação.</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
