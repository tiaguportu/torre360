<?php

namespace App\Filament\Resources\DocumentoInseridos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentoInseridosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('documentoObrigatorio.id')
                    ->searchable(),
                TextColumn::make('matricula.id')
                    ->searchable(),
                TextColumn::make('situacao_documento_inserido_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('arquivo_path')
                    ->searchable(),
                TextColumn::make('nome_arquivo_original')
                    ->searchable(),
                TextColumn::make('hash_arquivo')
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
