<?php

namespace App\Filament\Resources\TipoBolsas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TipoBolsasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('percentual_maximo')
                    ->label('% Máximo')
                    ->suffix('%')
                    ->sortable(),
                IconColumn::make('exige_aprovacao')
                    ->label('Exige Aprovação')
                    ->boolean(),
                TextColumn::make('bolsas_concedidas_count')
                    ->label('Bolsas Concedidas')
                    ->counts('bolsasConcedidas'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nome')
            ->stackedOnMobile();
    }
}
