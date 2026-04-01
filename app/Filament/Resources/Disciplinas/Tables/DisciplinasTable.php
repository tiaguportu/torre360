<?php

namespace App\Filament\Resources\Disciplinas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DisciplinasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('areaConhecimento.nome')
                    ->label('Área')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sigla')
                    ->searchable(),
                IconColumn::make('flag_matricula_automatica')
                    ->label('Matr. Automática')
                    ->boolean(),
                TextColumn::make('carga_horaria_semanal')
                    ->label('CH Semanal')
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
