<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ViewRecord;

class ViewCronogramaAula extends ViewRecord
{
    protected static string $resource = CronogramaAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Visualizar Cronograma de Aula')
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
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Visualização detalhada do registro de <strong>Cronograma de Aula</strong>.</p>';
        $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Ações disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';

        if ($user && $user->can('Update:CronogramaAula')) {
            $html .= '<li><strong>Editar:</strong> Abre o formulário para modificação dos dados deste registro.</li>';
        }

        if ($user && $user->can('Delete:CronogramaAula')) {
            $html .= '<li><strong>Excluir:</strong> Permite excluir este registro respeitando as travas de integridade.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
