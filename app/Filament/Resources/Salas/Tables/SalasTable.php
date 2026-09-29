<?php

namespace App\Filament\Resources\Salas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SalasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('unidade.nome')
                    ->label('Unidade')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('capacidade')
                    ->label('Capacidade')
                    ->numeric()
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('ativa')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('unidade')
                    ->relationship('unidade', 'nome')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('ativa'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
