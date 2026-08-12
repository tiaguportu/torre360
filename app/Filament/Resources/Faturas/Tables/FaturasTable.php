<?php

namespace App\Filament\Resources\Faturas\Tables;

use App\Enums\StatusFatura;
use App\Models\Banco;
use App\Models\Fatura;
use App\Models\TransacaoBancaria;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                Action::make('dar_baixa')
                    ->label('Dar Baixa')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Fatura $record) => $record->status !== StatusFatura::Pago)
                    ->schema([
                        Select::make('banco_id')
                            ->label('Banco')
                            ->options(Banco::where('is_active', true)->pluck('nome', 'id'))
                            ->required(),
                        TextInput::make('valor')
                            ->label('Valor Recebido (R$)')
                            ->prefix('R$')
                            ->numeric()
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
                        TransacaoBancaria::create([
                            'banco_id' => $data['banco_id'],
                            'fatura_id' => $record->id,
                            'tipo' => 'entrada',
                            'valor' => $data['valor'],
                            'data_transacao' => $data['data_transacao'],
                            'descricao' => $data['descricao'] ?? "Baixa manual — Fatura #{$record->id}",
                            'conciliado' => true,
                        ]);

                        // Recalcula o saldo devedor após inserção
                        $record->refresh();
                        $novoSaldo = $record->valor_restante;

                        if ($novoSaldo <= 0) {
                            $record->update(['status' => StatusFatura::Pago]);
                        } elseif ($record->status === StatusFatura::Pendente || $record->status === StatusFatura::Atrasado) {
                            $record->update(['status' => StatusFatura::Parcial]);
                        }

                        Notification::make()
                            ->title('Baixa registrada com sucesso!')
                            ->body($novoSaldo <= 0 ? 'Fatura marcada como PAGA.' : 'Pagamento parcial registrado. Saldo restante: R$ '.number_format(max(0, $novoSaldo), 2, ',', '.'))
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
            ->defaultSort('vencimento', 'asc')
            ->stackedOnMobile();
    }
}
