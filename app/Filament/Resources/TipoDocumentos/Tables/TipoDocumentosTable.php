<?php

namespace App\Filament\Resources\TipoDocumentos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TipoDocumentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('flag_obrigatorio')
                    ->label('Obrigatório')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('cursos.nome_interno')
                    ->label('Cursos')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('turmas.nome')
                    ->label('Turmas')
                    ->badge()
                    ->toggleable(),
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
            ])
            ->stackedOnMobile();
    }
}
