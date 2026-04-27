<?php

namespace App\Filament\Resources\CicloPreceptorias\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CicloPreceptoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('periodoLetivo.nome')
                    ->label('Período Letivo')
                    ->sortable(),

                TextColumn::make('data_inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('data_fim')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('preceptorias_count')
                    ->label('Preceptorias')
                    ->counts('preceptorias')
                    ->sortable(),
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
