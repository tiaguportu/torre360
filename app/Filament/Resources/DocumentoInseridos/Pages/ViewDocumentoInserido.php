<?php

namespace App\Filament\Resources\DocumentoInseridos\Pages;

use App\Filament\Resources\DocumentoInseridos\DocumentoInseridoResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentoInserido extends ViewRecord
{
    protected static string $resource = DocumentoInseridoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Visualizar Documento Inserido')
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
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Visualização detalhada do registro de <strong>Documento Inserido</strong>.</p>';
        $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Ações disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';

        if ($user && $user->can('Update:DocumentoInserido')) {
            $html .= '<li><strong>Editar:</strong> Abre o formulário para modificação dos dados deste registro.</li>';
        }

        if ($user && $user->can('Delete:DocumentoInserido')) {
            $html .= '<li><strong>Excluir:</strong> Permite excluir este registro respeitando as travas de integridade.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
