<?php

namespace App\Filament\Resources\ContaPagars\Tables;

use App\Enums\StatusContaPagar;
use App\Models\Banco;
use App\Models\ContaPagar;
use App\Models\TransacaoBancaria;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContaPagarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('fornecedor.razao_social')
                    ->label('Fornecedor')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (ContaPagar $record) => $record->status === StatusContaPagar::Atrasado ? 'danger' : null),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('centroCusto.nome')
                    ->label('Centro de Custo')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('planoConta.nome')
                    ->label('Plano de Contas')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusContaPagar::class)
                    ->multiple(),
                SelectFilter::make('fornecedor_id')
                    ->label('Fornecedor')
                    ->relationship('fornecedor', 'razao_social')
                    ->searchable(),
            ])
            ->recordActions([
                self::darBaixaAction(),
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

    public static function darBaixaAction(): Action
    {
        return Action::make('dar_baixa')
            ->label('Dar Baixa')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (ContaPagar $record): bool => ! in_array($record->status, [StatusContaPagar::Pago, StatusContaPagar::Cancelado]))
            ->schema([
                Select::make('banco_id')
                    ->label('Banco')
                    ->options(Banco::where('is_active', true)->pluck('nome', 'id'))
                    ->required(),
                DatePicker::make('data_pagamento')
                    ->label('Data do Pagamento')
                    ->native(false)
                    ->default(now())
                    ->required(),
            ])
            ->modalHeading('Dar Baixa na Conta a Pagar')
            ->modalDescription(fn (ContaPagar $record): string => "{$record->descricao} — R$ ".number_format((float) $record->valor, 2, ',', '.'))
            ->modalSubmitActionLabel('Confirmar Pagamento')
            ->action(function (array $data, ContaPagar $record): void {
                $transacao = TransacaoBancaria::create([
                    'banco_id' => $data['banco_id'],
                    'plano_conta_id' => $record->plano_conta_id,
                    'centro_custo_id' => $record->centro_custo_id,
                    'fornecedor_id' => $record->fornecedor_id,
                    'tipo' => 'saida',
                    'valor' => $record->valor,
                    'data_transacao' => $data['data_pagamento'],
                    'descricao' => "Pagamento — {$record->descricao}",
                    'conciliado' => true,
                ]);

                $record->update([
                    'status' => StatusContaPagar::Pago,
                    'data_pagamento' => $data['data_pagamento'],
                    'transacao_bancaria_id' => $transacao->id,
                ]);

                Notification::make()
                    ->title('Baixa registrada com sucesso!')
                    ->success()
                    ->send();
            });
    }
}
