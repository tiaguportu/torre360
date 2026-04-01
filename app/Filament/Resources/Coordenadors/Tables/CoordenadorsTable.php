<?php

namespace App\Filament\Resources\Coordenadors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoordenadorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('curso.nome_interno')
                    ->label('Curso')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('pessoa.nome')
                    ->label('Pessoa')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('cargo')
                    ->searchable(),
                TextColumn::make('data_inicio')
                    ->date()
                    ->sortable(),
                IconColumn::make('flag_somente_leitura')
                    ->label('Somente Leitura')
                    ->boolean(),
                TextColumn::make('created_at')
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
