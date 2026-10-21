<?php

namespace App\Filament\Resources\Salas\Pages;

use App\Filament\Resources\Salas\SalaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditSala extends EditRecord
{
    protected static string $resource = SalaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Sala')
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
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Nesta tela você pode revisar e atualizar os dados do registro selecionado de <strong>Sala</strong>.</p>';
        $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Ações disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';

        if ($user && $user->can('Update:Sala')) {
            $html .= '<li><strong>Salvar alterações:</strong> Grava no sistema as modificações feitas nos campos do formulário.</li>';
        }

        if ($user && $user->can('Delete:Sala')) {
            $html .= '<li><strong>Excluir:</strong> Permite remover este registro caso não possua vínculos dependentes.</li>';
        }

        if ($user && $user->can('View:Sala')) {
            $html .= '<li><strong>Visualizar:</strong> Exibe a ficha completa com os dados salvos deste registro.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
