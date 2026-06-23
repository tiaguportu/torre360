<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Resources\Contratos\ContratoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateContrato extends CreateRecord
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Contrato')
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

        $html = '<div class="space-y-4">';
        $html .= '<p>Esta página permite criar um novo contrato no sistema e associá-lo a uma matrícula ativa.</p>';
        $html .= '<h3>Como funciona?</h3>';
        $html .= '<ul>';
        $html .= '<li>Escolha o modelo de contrato (Template).</li>';
        $html .= '<li>Selecione o aluno correspondente na matrícula.</li>';
        $html .= '<li>Defina o valor total e, se desejar, associe os responsáveis financeiros com seus respectivos percentuais de rateio.</li>';
        $html .= '</ul>';

        $html .= '<hr class="my-4">';
        $html .= '<h3 class="text-lg font-bold">🛡️ Permissões e Acesso</h3>';
        $html .= '<ul class="list-disc ml-6 space-y-1">';
        if ($user->can('Create:Contrato')) {
            $html .= '<li>✅ Você tem permissão para cadastrar novos contratos.</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
