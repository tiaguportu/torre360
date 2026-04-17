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
                TextColumn::make('tipoDocumento.nome')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('matricula.codigo')
                    ->label('Matrícula')
                    ->description(fn ($record): string => $record->matricula?->pessoa?->nome ?? 'N/A')
                    ->searchable(['matricula.codigo', 'matricula.pessoa.nome'])
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),

                TextColumn::make('nome_arquivo_original')
                    ->label('Arquivo')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Data de Envio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Última Atualização')
                    ->dateTime('d/m/Y H:i')
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
            ])
            ->stackedOnMobile();
    }
}
