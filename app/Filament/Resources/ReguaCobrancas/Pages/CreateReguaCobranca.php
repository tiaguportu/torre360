<?php

namespace App\Filament\Resources\ReguaCobrancas\Pages;

use App\Filament\Resources\ReguaCobrancas\ReguaCobrancaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateReguaCobranca extends CreateRecord
{
    protected static string $resource = ReguaCobrancaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Régua de Cobrança')
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
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Ao cadastrar uma nova régua, defina o momento do gatilho e personalize o texto da mensagem.</p>';
        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Exemplos de Configuração:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Lembrete 7 dias antes:</strong> Escolha <em>Antes do Vencimento</em> e preencha <code>-7</code> no campo de dias.</li>';
        $html .= '<li><strong>Cobrança de 5 dias de atraso:</strong> Escolha <em>Após o Vencimento</em> e preencha <code>5</code> no campo de dias.</li>';
        $html .= '</ul>';
        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mt-2">Macros suportadas no texto:</h4>';
        $html .= '<p class="text-xs text-gray-500 font-mono bg-gray-100 dark:bg-gray-800 p-2 rounded">{{RESPONSAVEL_NOME}}, {{ALUNO_NOME}}, {{NUMERO_FATURA}}, {{VALOR}}, {{DATA_VENCIMENTO}}, {{DIAS_ATRASO}}, {{LINK_PAGAMENTO}}, {{PIX_COPIA_COLA}}</p>';
        $html .= '</div>';

        return $html;
    }
}
