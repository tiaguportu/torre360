<?php

namespace App\Filament\Resources\AcordoInadimplencias\Tables;

use App\Enums\StatusAcordoInadimplencia;
use App\Models\AcordoInadimplencia;
use App\Models\AcordoParcela;
use App\Services\AcordoInadimplenciaService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AcordoInadimplenciasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('matricula.pessoa.nome')
                    ->label('Estudante')
                    ->searchable()
                    ->sortable()
                    ->description(fn (AcordoInadimplencia $record) => "Resp: {$record->responsavelPessoa?->nome}"),

                TextColumn::make('valor_original_total')
                    ->label('Dívida Original')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('valor_desconto')
                    ->label('Desconto')
                    ->money('BRL')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('valor_total_acordo')
                    ->label('Valor Negociado')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('quantidade_parcelas')
                    ->label('Plano')
                    ->formatStateUsing(fn (AcordoInadimplencia $record) => "{$record->quantidade_parcelas}x de R$ ".number_format((float) $record->valor_parcela, 2, ',', '.'))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('aceito_em')
                    ->label('Aceite')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendente')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(StatusAcordoInadimplencia::class),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->url(function (AcordoInadimplencia $record) {
                        $tel = preg_replace('/\D/', '', (string) ($record->responsavelPessoa?->telefone ?? ''));
                        if (! $tel) {
                            return null;
                        }
                        if (strlen($tel) <= 11) {
                            $tel = '55'.$tel;
                        }
                        $texto = app(AcordoInadimplenciaService::class)->gerarMensagemWhatsapp($record);

                        return 'https://api.whatsapp.com/send?phone='.$tel.'&text='.rawurlencode($texto);
                    }, shouldOpenInNewTab: true)
                    ->visible(fn (AcordoInadimplencia $record) => filled($record->responsavelPessoa?->telefone)),

                Action::make('verTermo')
                    ->label('Termo de Confissão')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->modalHeading(fn (AcordoInadimplencia $record) => "Termo de Confissão e Transação de Dívida - {$record->codigo}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (AcordoInadimplencia $record) => view('filament.components.termo-confissao-modal', [
                        'acordo' => $record,
                    ])),

                Action::make('darBaixaParcela')
                    ->label('Baixar Parcela')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (AcordoInadimplencia $record) => in_array($record->status, [StatusAcordoInadimplencia::Ativo, StatusAcordoInadimplencia::AguardandoAceite], true))
                    ->form([
                        Select::make('acordo_parcela_id')
                            ->label('Selecione a Parcela a Quitar')
                            ->options(function (AcordoInadimplencia $record) {
                                return $record->parcelas()
                                    ->where('status', '!=', 'pago')
                                    ->get()
                                    ->mapWithKeys(fn (AcordoParcela $p) => [
                                        $p->id => ($p->numero_parcela === 0 ? 'Entrada' : "Parcela {$p->numero_parcela}")." - Vencimento {$p->data_vencimento->format('d/m/Y')} (R$ ".number_format((float) $p->valor, 2, ',', '.').')',
                                    ]);
                            })
                            ->required(),

                        TextInput::make('valor_pago')
                            ->label('Valor Recebido (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->required(),

                        Select::make('forma_pagamento')
                            ->label('Forma de Pagamento')
                            ->options([
                                'pix' => 'Pix',
                                'dinheiro' => 'Dinheiro em Espécie',
                                'cartao' => 'Cartão de Débito / Crédito',
                                'boleto' => 'Boleto Bancário',
                            ])
                            ->default('pix')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $parcela = AcordoParcela::findOrFail($data['acordo_parcela_id']);
                        app(AcordoInadimplenciaService::class)->registrarPagamentoParcela(
                            $parcela,
                            (float) $data['valor_pago'],
                            (string) $data['forma_pagamento']
                        );

                        Notification::make()
                            ->title('Parcela Quitada com Sucesso')
                            ->body('Recebimento de R$ '.number_format((float) $data['valor_pago'], 2, ',', '.').' registrado.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
