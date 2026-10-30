<?php

namespace App\Filament\Resources\ReguaCobrancas\Pages;

use App\Filament\Resources\ReguaCobrancas\ReguaCobrancaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditReguaCobranca extends EditRecord
{
    protected static string $resource = ReguaCobrancaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Régua de Cobrança')
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
        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Modifique os parâmetros do gatilho ou ajuste a redação da mensagem enviada aos responsáveis.</p>';
        $html .= '<p class="text-xs text-gray-500">Alterações entrarão em vigor imediatamente na próxima execução agendada (às 08:00) ou nas execuções manuais.</p>';
        $html .= '</div>';

        return $html;
    }
}
