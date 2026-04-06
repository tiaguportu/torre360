<?php

namespace App\Filament\Resources\Cidades\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CidadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('estado.nome')
                    ->label('Estado')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nome')
                    ->formatStateUsing(fn ($state, $record) => "{$state}-{$record->estado?->sigla}")
                    ->searchable()
                    ->sortable(),
                TextColumn::make('codigo_ibge')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
