<?php

namespace App\Filament\Resources\TransacaoBancarias\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransacaoBancariasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'banco',
                'fatura.contrato.matricula.pessoa',
                'planoConta',
                'centroCusto',
                'fornecedor',
            ]))
            ->columns([
                TextColumn::make('data_transacao')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'entrada' => 'success',
                        'saida' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'entrada' => '↑ Entrada',
                        'saida' => '↓ Saída',
                        default => ucfirst($state),
                    })
                    ->sortable(),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->color(fn ($record) => $record->tipo === 'entrada' ? 'success' : 'danger'),
                TextColumn::make('banco.nome')
                    ->label('Banco')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fatura.id')
                    ->label('Fatura')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contrato_via_fatura')
                    ->label('Aluno')
                    ->state(fn ($record) => $record->fatura?->contrato?->matricula?->pessoa?->nome ?? '—')
                    ->searchable(false),
                TextColumn::make('planoConta.nome')
                    ->label('Plano de Contas')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('centroCusto.nome')
                    ->label('Centro de Custo')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('fornecedor.razao_social')
                    ->label('Fornecedor')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
                IconColumn::make('conciliado')
                    ->label('Conciliado')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('external_id')
                    ->label('ID Externo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'entrada' => '↑ Entrada',
                        'saida' => '↓ Saída',
                    ]),
                SelectFilter::make('banco_id')
                    ->label('Banco')
                    ->relationship('banco', 'nome'),
                TernaryFilter::make('conciliado')
                    ->label('Conciliação')
                    ->trueLabel('Conciliado')
                    ->falseLabel('Não Conciliado'),
                Filter::make('periodo')
                    ->label('Período')
                    ->form([
                        DatePicker::make('data_de')
                            ->label('De')
                            ->native(false),
                        DatePicker::make('data_ate')
                            ->label('Até')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['data_de'], fn ($q, $date) => $q->whereDate('data_transacao', '>=', $date))
                            ->when($data['data_ate'], fn ($q, $date) => $q->whereDate('data_transacao', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['data_de'] ?? null) {
                            $indicators[] = 'De: '.date('d/m/Y', strtotime($data['data_de']));
                        }
                        if ($data['data_ate'] ?? null) {
                            $indicators[] = 'Até: '.date('d/m/Y', strtotime($data['data_ate']));
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data_transacao', 'desc')
            ->stackedOnMobile();
    }
}
