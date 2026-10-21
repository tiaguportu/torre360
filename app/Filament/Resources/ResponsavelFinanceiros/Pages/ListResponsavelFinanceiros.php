<?php

namespace App\Filament\Resources\ResponsavelFinanceiros\Pages;

use App\Filament\Resources\ResponsavelFinanceiros\ResponsavelFinanceiroResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListResponsavelFinanceiros extends ListRecords
{
    protected static string $resource = ResponsavelFinanceiroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Listagem de Responsável Financeiro')
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
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Listagem e gestão de registros de <strong>Responsável Financeiro</strong>.</p>';
        $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Ações disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';

        if ($user && $user->can('Create:ResponsavelFinanceiro')) {
            $html .= '<li><strong>Novo registro:</strong> Permite cadastrar um novo Responsável Financeiro.</li>';
        }

        if ($user && $user->can('Update:ResponsavelFinanceiro')) {
            $html .= '<li><strong>Editar:</strong> Altera os dados dos registros existentes.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
