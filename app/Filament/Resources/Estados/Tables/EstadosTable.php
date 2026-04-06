<?php

namespace App\Filament\Resources\Estados\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EstadosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pais.nome')
                    ->label('País')
                    ->formatStateUsing(fn ($state, $record) => ($record->pais?->sigla ? mb_convert_encoding('&#'.(127397 + ord(strtoupper($record->pais->sigla[0]))).';&#'.(127397 + ord(strtoupper($record->pais->sigla[1]))).';', 'UTF-8', 'HTML-ENTITIES').' ' : '').$state
                    )
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sigla')
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
