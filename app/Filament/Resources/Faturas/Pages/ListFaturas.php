<?php

namespace App\Filament\Resources\Faturas\Pages;

use App\Enums\StatusFatura;
use App\Filament\Resources\Faturas\FaturaResource;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Services\ReguaCobrancaService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFaturas extends ListRecords
{
    protected static string $resource = FaturaResource::class;

    public function getTabs(): array
    {
        $baseQuery = fn () => FaturaResource::getEloquentQuery();

        $emAberto = [StatusFatura::Pendente, StatusFatura::Parcial, StatusFatura::Atrasado];

        return [
            'todas' => Tab::make('Todas')
                ->badge($baseQuery()->count()),
            'a_vencer' => Tab::make('A Vencer')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', $emAberto)
                    ->whereDate('vencimento', '>=', today()))
                ->badge($baseQuery()->whereIn('status', $emAberto)->whereDate('vencimento', '>=', today())->count())
                ->badgeColor('info'),
            'vencidas' => Tab::make('Vencidas')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', $emAberto)
                    ->whereDate('vencimento', '<', today()))
                ->badge($baseQuery()->whereIn('status', $emAberto)->whereDate('vencimento', '<', today())->count())
                ->badgeColor('danger'),
            'pagas' => Tab::make('Pagas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StatusFatura::Pago))
                ->badge($baseQuery()->where('status', StatusFatura::Pago)->count())
                ->badgeColor('success'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerarLote')
                ->label('Criação em Lote')
                ->icon('heroicon-o-rectangle-stack')
                ->color('primary')
                ->form([
                    Select::make('contrato_id')
                        ->label('Contrato')
                        ->options(Contrato::all()->mapWithKeys(fn ($c) => [$c->id => "Nº {$c->id} - Valor: R$ ".number_format($c->valor_total, 2, ',', '.')]))
                        ->searchable()
                        ->required(),
                    TextInput::make('parcelas')
                        ->label('Quantidade de Parcelas')
                        ->numeric()
                        ->default(12)
                        ->required(),
                    Toggle::make('por_matricula')
                        ->label('Criar uma fatura por cada matrícula?')
                        ->default(true)
                        ->helperText('Se marcado, o valor total será dividido pelo número de matrículas e parcelas.'),
                ])
                ->action(function (array $data) {
                    $contrato = Contrato::with('matricula.turma.periodoLetivo')->find($data['contrato_id']);
                    if (! $contrato) {
                        return;
                    }

                    $numParcelas = (int) $data['parcelas'];
                    $valorTotal = $contrato->valor_total;
                    $startDate = Carbon::parse($contrato->data_aceite);

                    $matricula = $contrato->matricula;
                    $numMatriculas = $matricula ? 1 : 0;

                    if ($data['por_matricula']) {
                        // Cria parcelas para cada matrícula
                        $valorPorFatura = $valorTotal / (max(1, $numMatriculas) * $numParcelas);

                        if ($matricula) {
                            $startDate = $contrato->data_aceite ? Carbon::parse($contrato->data_aceite)->startOfDay() : now()->startOfDay();

                            // Se não houver data_fim no período letivo, usamos o fallback de meses
                            $origEndDate = ($matricula->turma?->periodoLetivo?->data_fim)
                                ? Carbon::parse($matricula->turma->periodoLetivo->data_fim)->startOfDay()
                                : $startDate->copy()->addMonths($numParcelas);

                            // O último vencimento NÃO PODE ser superior a data_fim do PeriodoLetivo
                            // Se a data_fim for anterior ao contrato, travamos no startDate
                            $endDate = $origEndDate->lt($startDate) ? $startDate->copy() : $origEndDate;

                            $totalIntervalInDays = $endDate->diffInDays($startDate);
                            $intervalBetweenPayments = $numParcelas > 1 ? $totalIntervalInDays / ($numParcelas - 1) : 0;

                            for ($i = 0; $i < $numParcelas; $i++) {
                                $vencimento = $startDate->copy()->addDays(round($i * $intervalBetweenPayments));

                                Fatura::create([
                                    'contrato_id' => $contrato->id,
                                    'vencimento' => $vencimento,
                                    'valor' => $valorPorFatura,
                                    'status' => 'pendente',
                                ]);
                            }
                        }
                    } else {
                        // Cria parcelas globais para o contrato
                        $valorPorFatura = $valorTotal / $numParcelas;

                        $startDate = $contrato->data_aceite ? Carbon::parse($contrato->data_aceite)->startOfDay() : now()->startOfDay();

                        $origEndDate = ($matricula?->turma?->periodoLetivo?->data_fim)
                            ? Carbon::parse($matricula->turma->periodoLetivo->data_fim)->startOfDay()
                            : $startDate->copy()->addMonths($numParcelas);

                        $endDate = $origEndDate->lt($startDate) ? $startDate->copy() : $origEndDate;

                        $totalIntervalInDays = $endDate->diffInDays($startDate);
                        $intervalBetweenPayments = $numParcelas > 1 ? $totalIntervalInDays / ($numParcelas - 1) : 0;

                        for ($i = 0; $i < $numParcelas; $i++) {
                            $vencimento = $startDate->copy()->addDays(round($i * $intervalBetweenPayments));

                            Fatura::create([
                                'contrato_id' => $contrato->id,
                                'vencimento' => $vencimento,
                                'valor' => $valorPorFatura,
                                'status' => 'pendente',
                            ]);
                        }
                    }

                    Notification::make()
                        ->title('Faturas geradas com sucesso!')
                        ->success()
                        ->send();
                }),
            Action::make('executar_regua')
                ->label('Executar Régua do Dia')
                ->icon('heroicon-o-bell-alert')
                ->color('warning')
                ->visible(fn () => auth()->user()?->can('Execute:ReguaCobranca') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Processar Régua de Cobrança')
                ->modalDescription('Deseja analisar as faturas que atingiram a data alvo das réguas ativas e enviar os lembretes aos responsáveis?')
                ->action(function () {
                    $res = app(ReguaCobrancaService::class)->processarReguaDiaria();
                    Notification::make()
                        ->title('Régua de Cobrança Processada!')
                        ->body("{$res['total_notificacoes_enviadas']} notificação(ões) enviada(s) para {$res['total_faturas_analisadas']} fatura(s) analisada(s).")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão Financeira (Faturas)')
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

        $canCreate = $user->can('Create:Fatura');
        $canUpdate = $user->can('Update:Fatura');
        $canExecuteRegua = $user->can('Execute:ReguaCobranca');

        $html = '<p>Nesta página você gerencia a cobrança dos contratos através das faturas e monitora o fluxo de recebimentos.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Criação em Lote:</strong> Ferramenta para gerar rapidamente todas as parcelas de um contrato de uma só vez.</li>';
        $html .= '<li><strong>Cobrança Pontual (Ação na Tabela):</strong> Use o botão <em>"Cobrar"</em> em qualquer fatura em aberto para disparar na hora uma mensagem personalizada via E-mail, Portal ou Push.</li>';
        $html .= '<li><strong>Histórico de Lembretes:</strong> Veja a lista completa de notificações de cobrança já enviadas para cada fatura.</li>';

        if ($canExecuteRegua) {
            $html .= '<li><strong>Executar Régua do Dia:</strong> Dispare manualmente a verificação da régua de cobrança automática para as faturas de hoje.</li>';
        }

        if ($canCreate) {
            $html .= '<li><strong>Nova Fatura:</strong> Crie uma cobrança avulsa ou manual vinculada a um contrato.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Status e Pagamento:</strong> Registre baixas manuais parciais ou integrais das faturas.</li>';
        }

        $html .= '<li><strong>Abas de Vencimento:</strong> Acompanhe rapidamente faturas <em>A Vencer</em>, <em>Vencidas</em> e <em>Pagas</em>.</li>';
        $html .= '</ul>';

        return $html;
    }
}
