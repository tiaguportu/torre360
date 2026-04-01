<?php

namespace App\Filament\Resources\Cursos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CursosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unidade.nome')
                    
                    ->sortable(),
                TextColumn::make('nome_externo')
                    ->searchable(),
                TextColumn::make('nome_interno')
                    ->searchable(),
                TextColumn::make('portaria')
                    ->searchable(),
                TextColumn::make('data_final')
                    ->date()
                    ->sortable(),
                TextColumn::make('minutos_por_periodo')
                    ->numeric()
                    ->sortable(),
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
            ]);
    }
}
