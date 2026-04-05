<?php

namespace App\Filament\Resources\DocumentoObrigatorios\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentoObrigatoriosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('curso.nome_interno')

                    ->sortable(),
                TextColumn::make('nome')
                    ->searchable(),
                IconColumn::make('flag_obrigatorio')
                    ->boolean(),
                IconColumn::make('flag_ativo')
                    ->boolean(),
                TextColumn::make('modelo_arquivo')
                    ->label('Arquivo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('modelo_link')
                    ->label('Link')
                    ->toggleable(isToggledHiddenByDefault: true),
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
