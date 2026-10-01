<?php

namespace App\Filament\Resources\Faturas\Tables;

use App\Enums\StatusFatura;
use App\Models\Banco;
use App\Models\Fatura;
use App\Models\ReguaCobranca;
use App\Services\BaixaFaturaService;
use App\Services\GatewayPagamentoManager;
use App\Services\PagamentoConfirmacaoService;
use App\Services\ReguaCobrancaService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class FaturasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Nº')
                    ->sortable()
                    ->width('60px'),
                TextColumn::make('contrato.matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contrato_id')
                    ->label('Contrato')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->sortable(),
                TextColumn::make('vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Fatura $record) => $record->status === StatusFatura::Atrasado ? 'danger' : null),
                TextColumn::make('valor_bruto')
                    ->label('Valor (Bruto)')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('valor')
                    ->label('Valor a Pagar')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('valor_pago')
                    ->label('Total Pago')
                    ->money('BRL')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('valor_restante')
                    ->label('Saldo Devedor')
                    ->money('BRL')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusFatura::class)
                    ->multiple(),
                Filter::make('vencimento')
                    ->label('Período de Vencimento')
                    ->form([
                        DatePicker::make('vencimento_de')
                            ->label('De')
                            ->native(false),
                        DatePicker::make('vencimento_ate')
                            ->label('Até')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['vencimento_de'], fn ($q, $date) => $q->whereDate('vencimento', '>=', $date))
                            ->when($data['vencimento_ate'], fn ($q, $date) => $q->whereDate('vencimento', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['vencimento_de'] ?? null) {
                            $indicators[] = 'Vencimento a partir de: '.date('d/m/Y', strtotime($data['vencimento_de']));
                        }
                        if ($data['vencimento_ate'] ?? null) {
                            $indicators[] = 'Vencimento até: '.date('d/m/Y', strtotime($data['vencimento_ate']));
                        }

                        return $indicators;
                    }),
                Filter::make('em_aberto')
                    ->label('Somente em aberto')
                    ->query(fn (Builder $query) => $query->whereIn('status', [StatusFatura::Pendente->value, StatusFatura::Atrasado->value]))
                    ->toggle(),
            ])
            ->recordActions([
                self::darBaixaAction(),
                self::gerarCobrancaAction(),
                self::verDadosPagamentoAction(),
                self::simularPagamentoAction(),
                self::enviarCobrancaAction(),
                self::historicoCobrancasAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('vencimento', 'asc')
            ->stackedOnMobile();
    }

    /**
     * Ação para disparar lembrete de cobrança pontual para os responsáveis da fatura.
     */
    public static function enviarCobrancaAction(): Action
    {
        return Action::make('enviar_cobranca')
            ->label('Cobrar')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->visible(fn (Fatura $record): bool => ! in_array($record->status, [StatusFatura::Pago, StatusFatura::Cancelado]))
            ->modalHeading(fn (Fatura $record): string => "Enviar Lembrete de Cobrança — Fatura #{$record->id}")
            ->modalDescription(fn (Fatura $record): string => 'Aluno: '.($record->contrato?->matricula?->pessoa?->nome ?? 'N/I').' — Vencimento: '.($record->vencimento?->format('d/m/Y') ?? 'N/I').' — Saldo: R$ '.number_format($record->valor_restante, 2, ',', '.'))
            ->modalSubmitActionLabel('Disparar Notificação')
            ->schema([
                Select::make('regua_id')
                    ->label('Modelo de Régua / Mensagem')
                    ->options(ReguaCobranca::where('is_ativo', true)->orderBy('ordem')->pluck('nome', 'id'))
                    ->placeholder('Selecione uma régua ou digite abaixo...')
                    ->searchable(),

                Select::make('canal')
                    ->label('Canal de Envio')
                    ->options([
                        'todos' => 'Todos os Canais (E-mail, Portal e Push)',
                        'email' => 'Apenas E-mail',
                        'portal' => 'Apenas Portal da Família',
                        'push' => 'Apenas Push Notification',
                    ])
                    ->default('todos')
                    ->required(),

                TextInput::make('assunto_personalizado')
                    ->label('Assunto Personalizado (Opcional)')
                    ->placeholder('Deixe em branco para usar o da régua'),

                Textarea::make('mensagem_personalizada')
                    ->label('Mensagem Personalizada (Opcional)')
                    ->placeholder('Caso queira enviar uma mensagem avulsa exclusiva, digite aqui...')
                    ->rows(3)
                    ->helperText('Se informado, substituirá o texto da régua selecionada.'),
            ])
            ->action(function (array $data, Fatura $record): void {
                $regra = ! empty($data['regua_id']) ? ReguaCobranca::find($data['regua_id']) : null;
                $msg = ! empty($data['mensagem_personalizada']) ? $data['mensagem_personalizada'] : null;
                $assunto = ! empty($data['assunto_personalizado']) ? $data['assunto_personalizado'] : null;
                $canal = $data['canal'] ?? 'todos';

                try {
                    $res = app(ReguaCobrancaService::class)->dispararLembreteManual(
                        $record,
                        $regra,
                        $msg,
                        $assunto,
                        $canal
                    );

                    Notification::make()
                        ->title('Lembrete de Cobrança Enviado!')
                        ->body("Notificação enviada com sucesso para {$res['total_enviados']} responsável(is).")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Erro ao enviar cobrança')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Ação para visualizar o histórico de lembretes enviados para esta fatura.
     */
    public static function historicoCobrancasAction(): Action
    {
        return Action::make('historico_cobrancas')
            ->label('Histórico de Lembretes')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->modalHeading(fn (Fatura $record): string => "Histórico de Cobranças — Fatura #{$record->id}")
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->form([
                Placeholder::make('logs')
                    ->label('')
                    ->content(function (Fatura $record) {
                        $logs = $record->cobrancaLogs()->with('reguaCobranca', 'pessoa')->latest()->get();

                        if ($logs->isEmpty()) {
                            return new HtmlString('<p class="text-sm text-gray-500 py-3 text-center">Nenhum lembrete de cobrança disparado para esta fatura até o momento.</p>');
                        }

                        $html = '<div class="space-y-3 max-h-72 overflow-y-auto">';
                        foreach ($logs as $log) {
                            $regraNome = $log->reguaCobranca?->nome ?? 'Disparo Manual';
                            $data = $log->created_at?->format('d/m/Y H:i') ?? $log->data_envio?->format('d/m/Y');
                            $dest = $log->pessoa?->nome ?? $log->destinatario;
                            $canal = ucfirst($log->canal);
                            $badgeColor = $log->status_envio === 'sucesso' ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30' : 'text-red-600 bg-red-50 dark:bg-red-900/30';

                            $html .= "<div class='p-3 border rounded-lg bg-gray-50 dark:bg-gray-800/60 border-gray-200 dark:border-gray-700 text-xs space-y-1'>";
                            $html .= "<div class='flex justify-between items-center'><span class='font-bold text-gray-800 dark:text-gray-200'>{$regraNome}</span><span class='px-2 py-0.5 rounded text-[10px] font-semibold {$badgeColor}'>".ucfirst($log->status_envio).'</span></div>';
                            $html .= "<div class='text-gray-500'><strong>Destinatário:</strong> {$dest} ({$canal}) • <strong>Data:</strong> {$data}</div>";
                            $html .= "<div class='text-gray-600 dark:text-gray-300 italic text-[11px] pt-1 border-t border-gray-100 dark:border-gray-700'>".e($log->mensagem_enviada).'</div>';
                            $html .= '</div>';
                        }
                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
            ]);
    }

    /**
     * Ação de baixa manual de pagamento, compartilhada entre a listagem de Faturas
     * e o RelationManager de Faturas dentro de Contrato.
     */
    public static function darBaixaAction(): Action
    {
        return Action::make('dar_baixa')
            ->label('Dar Baixa')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Fatura $record) => ! in_array($record->status, [StatusFatura::Pago, StatusFatura::Cancelado]))
            ->schema([
                Select::make('banco_id')
                    ->label('Banco')
                    ->options(Banco::where('is_active', true)->pluck('nome', 'id'))
                    ->required(),
                TextInput::make('valor')
                    ->label('Valor Recebido (R$)')
                    ->prefix('R$')
                    ->numeric()
                    ->minValue(0.01)
                    ->required()
                    ->default(fn (Fatura $record) => $record->valor_restante),
                DatePicker::make('data_transacao')
                    ->label('Data do Pagamento')
                    ->required()
                    ->default(now())
                    ->native(false),
                TextInput::make('descricao')
                    ->label('Observação')
                    ->placeholder('Ex: Pago via PIX, Boleto, Dinheiro...'),
            ])
            ->modalHeading('Dar Baixa na Fatura')
            ->modalDescription(fn (Fatura $record) => "Fatura #{$record->id} — Saldo devedor: R$ ".number_format($record->valor_restante, 2, ',', '.'))
            ->modalSubmitActionLabel('Confirmar Pagamento')
            ->action(function (array $data, Fatura $record): void {
                app(BaixaFaturaService::class)->darBaixa($record, [
                    'banco_id' => $data['banco_id'],
                    'valor' => $data['valor'],
                    'data_transacao' => $data['data_transacao'],
                    'descricao' => $data['descricao'] ?? "Baixa manual — Fatura #{$record->id}",
                ]);

                $novoSaldo = $record->refresh()->valor_restante;

                Notification::make()
                    ->title('Baixa registrada com sucesso!')
                    ->body($novoSaldo <= 0 ? 'Fatura marcada como PAGA.' : 'Pagamento parcial registrado. Saldo restante: R$ '.number_format(max(0, $novoSaldo), 2, ',', '.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Gera a cobrança (PIX/boleto/link) no gateway configurado e grava os dados na
     * fatura. Só aparece se ainda não houver uma cobrança gerada (gateway_id vazio) —
     * gerar de novo depois de pago/cancelado não faz sentido.
     */
    public static function gerarCobrancaAction(): Action
    {
        return Action::make('gerar_cobranca')
            ->label('Gerar Cobrança')
            ->icon('heroicon-o-qr-code')
            ->color('info')
            ->visible(fn (Fatura $record): bool => ! $record->gateway_id
                && ! in_array($record->status, [StatusFatura::Pago, StatusFatura::Cancelado]))
            ->requiresConfirmation()
            ->modalDescription(fn (Fatura $record): string => "Gera PIX/boleto para a fatura #{$record->id} (saldo R$ ".number_format($record->valor_restante, 2, ',', '.').') via '.GatewayPagamentoManager::resolver()->rotulo().'.')
            ->action(function (Fatura $record): void {
                $dados = GatewayPagamentoManager::resolver()->criarCobranca($record);
                $record->update($dados);

                Notification::make()
                    ->title('Cobrança gerada com sucesso!')
                    ->success()
                    ->send();
            });
    }

    /**
     * Mostra os dados da cobrança já gerada (PIX copia-e-cola, linha digitável, links).
     */
    public static function verDadosPagamentoAction(): Action
    {
        return Action::make('ver_dados_pagamento')
            ->label('Dados de Pagamento')
            ->icon('heroicon-o-banknotes')
            ->color('gray')
            ->visible(fn (Fatura $record): bool => (bool) $record->gateway_id)
            ->modalHeading(fn (Fatura $record): string => "Dados de Pagamento — Fatura #{$record->id}")
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->schema([
                Placeholder::make('dados')
                    ->label('')
                    ->content(function (Fatura $record) {
                        $html = '<div class="space-y-3 text-sm">';
                        $html .= '<p><strong>Gateway:</strong> '.e($record->gateway).'</p>';
                        $html .= '<p><strong>ID da Cobrança:</strong> '.e($record->gateway_id).'</p>';
                        $html .= '<p><strong>Status no Gateway:</strong> '.e($record->status_gateway).'</p>';

                        if ($record->pix_copia_e_cola) {
                            $html .= '<div><strong>PIX Copia e Cola:</strong><textarea readonly class="w-full text-xs p-2 mt-1 border rounded font-mono" rows="3">'.e($record->pix_copia_e_cola).'</textarea></div>';
                        }

                        if ($record->linha_digitavel) {
                            $html .= '<p><strong>Linha Digitável:</strong> '.e($record->linha_digitavel).'</p>';
                        }

                        if ($record->boleto_url) {
                            $html .= '<p><a href="'.e($record->boleto_url).'" target="_blank" class="text-primary-600 underline">Baixar Boleto</a></p>';
                        }

                        if ($record->link_pagamento) {
                            $html .= '<p><a href="'.e($record->link_pagamento).'" target="_blank" class="text-primary-600 underline">Abrir Link de Pagamento</a></p>';
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
            ]);
    }

    /**
     * Visível apenas com o driver "fake" ativo: simula a confirmação de pagamento que
     * normalmente viria do webhook do gateway, usando a mesma lógica idempotente
     * (`PagamentoConfirmacaoService`) — útil para testar/demonstrar o fluxo completo sem
     * um gateway real configurado.
     */
    public static function simularPagamentoAction(): Action
    {
        return Action::make('simular_pagamento')
            ->label('Simular Pagamento (Dev)')
            ->icon('heroicon-o-beaker')
            ->color('warning')
            ->visible(fn (Fatura $record): bool => config('pagamentos.driver') === 'fake'
                && (bool) $record->gateway_id
                && ! in_array($record->status, [StatusFatura::Pago, StatusFatura::Cancelado]))
            ->requiresConfirmation()
            ->modalDescription('Simula a confirmação de pagamento vinda do gateway fake, como se o webhook tivesse chegado. Só aparece com o driver "fake" ativo.')
            ->action(function (Fatura $record): void {
                $resultado = app(PagamentoConfirmacaoService::class)->confirmar(
                    $record,
                    $record->valor_restante,
                    now()->toDateString(),
                    'sim_'.$record->gateway_id.'_'.now()->timestamp
                );

                $notification = Notification::make();

                if ($resultado['processado']) {
                    $notification->title('Pagamento simulado com sucesso!')->success();
                } else {
                    $notification->title('Não processado: '.$resultado['motivo'])->warning();
                }

                $notification->send();
            });
    }
}
