<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Resources\Contratos\ContratoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListContratos extends ListRecords
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Contratos')
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
        
        $canCreate = $user->can('Create:Contrato');
        $canUpdate = $user->can('Update:Contrato');

        $html = '<p>Aqui você gerencia os contratos financeiros vinculados às matrículas.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Visualizar:</strong> Acompanhe o status de assinatura e valores totais dos contratos.</li>';
        
        if ($canCreate) {
            $html .= '<li><strong>Gerar Contrato:</strong> Normalmente os contratos são gerados a partir da matrícula, mas podem ser criados manualmente aqui se necessário.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Ajuste valores, adicione responsáveis financeiros e registre a data de aceite.</li>';
        }

        $html .= '<li><strong>Faturas:</strong> Os contratos são a base para a geração automática das faturas mensais.</li>';
        $html .= '</ul>';

        return $html;
    }
}
