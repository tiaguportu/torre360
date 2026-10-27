<?php

namespace App\Filament\Resources\ContaPagars\Tables;

use App\Enums\StatusContaPagar;
use App\Models\Banco;
use App\Models\ContaPagar;
use App\Models\TransacaoBancaria;
use App\Services\ContaRecorrenteService;
use App\Support\PermissaoAcao;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->description(fn (ContaPagar $record): ?string => $record->conta_recorrente_id && ! $record->valor_revisado ? 'valor estimado: a revisar' : null)
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
                Filter::make('a_revisar')
                    ->label('Valor a revisar')
                    ->query(fn (Builder $query) => $query->whereNotNull('conta_recorrente_id')->where('valor_revisado', false)),
                SelectFilter::make('fornecedor_id')
                    ->label('Fornecedor')
                    ->relationship('fornecedor', 'razao_social')
                    ->searchable(),
            ])
            ->recordActions([
                self::revisarValorAction(),
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

    /**
     * Confirma o valor real da fatura de uma conta gerada de despesa recorrente de valor estimado (Onda 78).
     */
    public static function revisarValorAction(): Action
    {
        return Action::make('revisar_valor')
            ->label('Revisar valor')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->authorize(PermissaoAcao::qualquer('Update:ContaPagar'))
            ->visible(fn (ContaPagar $record): bool => $record->conta_recorrente_id !== null && ! $record->valor_revisado && ! in_array($record->status, [StatusContaPagar::Pago, StatusContaPagar::Cancelado], true))
            ->modalHeading('Revisar o valor da conta')
            ->modalDescription('Confira o valor da fatura que chegou e confirme. Marque a opção abaixo se o novo valor passa a valer para as próximas contas desta despesa.')
            ->fillForm(fn (ContaPagar $record): array => ['valor' => (float) $record->valor])
            ->schema([
                TextInput::make('valor')->label('Valor da fatura (R$)')->numeric()->minValue(0.01)->required()->prefix('R$'),
                Toggle::make('aplicar_ao_modelo')->label('Usar este valor como o novo valor estimado da despesa')->default(false),
            ])
            ->action(function (array $data, ContaPagar $record): void {
                try {
                    app(ContaRecorrenteService::class)->revisarValor($record, (float) $data['valor'], (bool) ($data['aplicar_ao_modelo'] ?? false), auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Revisão recusada')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Valor revisado')->success()->send();
            });
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
                if ($record->conta_recorrente_id && ! $record->valor_revisado) {
                    Notification::make()->title('Revise o valor antes de pagar')->body('Esta conta nasceu de uma despesa de valor estimado: confirme o valor da fatura em "Revisar valor".')->warning()->send();

                    return;
                }

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
