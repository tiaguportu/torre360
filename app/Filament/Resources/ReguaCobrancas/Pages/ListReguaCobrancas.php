<?php

namespace App\Filament\Resources\ReguaCobrancas\Pages;

use App\Filament\Resources\ReguaCobrancas\ReguaCobrancaResource;
use App\Services\ReguaCobrancaService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListReguaCobrancas extends ListRecords
{
    protected static string $resource = ReguaCobrancaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova Régua de Cobrança')
                ->visible(fn () => auth()->user()?->can('Create:ReguaCobranca') ?? false),

            Action::make('executar_regua')
                ->label('Executar Régua do Dia')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn () => auth()->user()?->can('Execute:ReguaCobranca') ?? false)
                ->modalHeading('Processar Régua de Cobrança Diária')
                ->modalDescription('O sistema analisará todas as faturas com vencimento previsto pelas réguas ativas e disparará as notificações multicanal correspondentes.')
                ->schema([
                    DatePicker::make('data_referencia')
                        ->label('Data de Referência')
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),

                    Toggle::make('dry_run')
                        ->label('Modo Simulação (Apenas contar, sem disparar mensagens)')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $dataRef = Carbon::parse($data['data_referencia']);
                    $dryRun = (bool) $data['dry_run'];

                    $resultado = app(ReguaCobrancaService::class)->processarReguaDiaria($dataRef, $dryRun);

                    Notification::make()
                        ->title($dryRun ? 'Simulação da Régua Concluída!' : 'Régua de Cobrança Processada!')
                        ->body("Total de {$resultado['total_notificacoes_enviadas']} notificação(ões) gerada(s) para {$resultado['total_faturas_analisadas']} fatura(s) analisada(s).")
                        ->success()
                        ->send();
                }),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Régua de Cobrança Inteligente')
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
        $canCreate = $user?->can('Create:ReguaCobranca') ?? false;
        $canExecute = $user?->can('Execute:ReguaCobranca') ?? false;

        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">A <strong>Régua de Cobrança Inteligente</strong> automatiza o acompanhamento preventivo e de combate à inadimplência escolar, enviando lembretes multicanal nos momentos ideais do ciclo de pagamento.</p>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Como funciona o mecanismo de gatilho:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Dias Negativos (ex: -5 ou -2):</strong> Lembretes preventivos enviados dias antes da data de vencimento da fatura.</li>';
        $html .= '<li><strong>Dia Zero (0):</strong> Notificação de "Vence Hoje" enviada pontualmente no dia em que o boleto ou parcela expira.</li>';
        $html .= '<li><strong>Dias Positivos (ex: 3, 7 ou 15):</strong> Réguas escalonadas de atraso e cobrança amigável para faturas não quitadas.</li>';
        $html .= '</ul>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mt-3">Suas permissões e recursos disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Testar Disparo:</strong> Na tabela, utilize a ação <em>"Testar"</em> para disparar uma prévia com dados de uma fatura real.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Nova Régua:</strong> Você pode criar novos pontos de contato personalizados com macros dinâmicas.</li>';
        }

        if ($canExecute) {
            $html .= '<li><strong>Executar Régua do Dia:</strong> Dispare a verificação manual a qualquer momento, inclusive em modo simulação.</li>';
        }

        $html .= '<li><strong>Execução Noturna Automática:</strong> O sistema executa automaticamente a régua todos os dias às 08:00 via agendador cron.</li>';
        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
