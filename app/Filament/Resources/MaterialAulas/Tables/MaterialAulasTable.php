<?php

namespace App\Filament\Resources\MaterialAulas\Tables;

use App\Enums\TipoMaterialAula;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaterialAulasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable(),
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('data_publicacao')
                    ->label('Publicação')
                    ->date('d/m/Y')
                    ->sortable(),
                IconColumn::make('visivel')
                    ->label('Visível')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoMaterialAula::class),
                SelectFilter::make('turma_id')
                    ->label('Turma')
                    ->relationship('turma', 'nome')
                    ->searchable(),
                SelectFilter::make('disciplina_id')
                    ->label('Disciplina')
                    ->relationship('disciplina', 'nome')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data_publicacao', 'desc')
            ->stackedOnMobile();
    }
}
