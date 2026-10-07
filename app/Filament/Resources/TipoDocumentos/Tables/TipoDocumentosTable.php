<?php

declare(strict_types=1);

namespace App\Filament\Resources\TipoDocumentos\Tables;

use App\Enums\CategoriaExigenciaDocumento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TipoDocumentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Tipo de Documento')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('categoria_exigencia')
                    ->label('Exigência')
                    ->badge()
                    ->sortable(),

                TextColumn::make('cursos.nome_interno')
                    ->label('Cursos Vinculados')
                    ->badge()
                    ->placeholder('Todos os Cursos')
                    ->toggleable(),

                TextColumn::make('turmas.nome')
                    ->label('Turmas Vinculadas')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('modelo_arquivo')
                    ->label('Arquivo')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('modelo_link')
                    ->label('Link')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('categoria_exigencia')
                    ->label('Categoria de Exigência')
                    ->options(CategoriaExigenciaDocumento::class),
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
