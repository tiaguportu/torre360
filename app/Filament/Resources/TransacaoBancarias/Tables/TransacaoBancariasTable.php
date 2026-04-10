<?php

namespace App\Filament\Resources\TransacaoBancarias\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransacaoBancariasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('banco.id')
                    ->searchable(),
                TextColumn::make('fatura.id')
                    ->searchable(),
                TextColumn::make('planoConta.id')
                    ->searchable(),
                TextColumn::make('centroCusto.id')
                    ->searchable(),
                TextColumn::make('fornecedor.nome_cnpj')
                    ->label('Fornecedor')
                    ->searchable(['razao_social', 'cnpj']),
                TextColumn::make('tipo')
                    ->badge(),
                TextColumn::make('valor')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('data_transacao')
                    ->date()
                    ->sortable(),
                TextColumn::make('descricao')
                    ->searchable(),
                IconColumn::make('conciliado')
                    ->boolean(),
                TextColumn::make('external_id')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
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
