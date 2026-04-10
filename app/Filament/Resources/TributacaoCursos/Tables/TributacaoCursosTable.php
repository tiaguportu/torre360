<?php

namespace App\Filament\Resources\TributacaoCursos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TributacaoCursosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('curso.nome_interno')

                    ->sortable(),
                TextColumn::make('cnae')
                    ->searchable(),
                TextColumn::make('iss')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pis')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cofins')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('item_servico')
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
