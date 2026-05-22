<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditAvaliacao extends EditRecord
{
    protected static string $resource = AvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Avaliação')
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

        $canUpdate = $user->can('Update:Avaliacao');
        $canDelete = $user->can('Delete:Avaliacao');

        $html = '<p>Nesta página você pode visualizar ou alterar os detalhes de uma avaliação já cadastrada no sistema.</p>';
        $html .= '<h3>Ações Disponíveis</h3>';
        $html .= '<ul>';

        if ($canUpdate) {
            $html .= '<li><strong>Salvar Alterações:</strong> Modifique os campos desejados (ex: descrição, data ou peso) e clique em "Salvar alterações" no rodapé.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Excluir:</strong> Use o botão "Excluir" no topo da página para remover esta avaliação do sistema. Atenção: Isso pode não ser permitido se já houver notas lançadas.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
