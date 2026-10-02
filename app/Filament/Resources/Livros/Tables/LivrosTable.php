<?php

namespace App\Filament\Resources\Livros\Tables;

use App\Models\Livro;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LivrosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('autor')
                    ->label('Autor')
                    ->searchable(),
                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('isbn')
                    ->label('ISBN')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('quantidade_disponivel')
                    ->label('Disponíveis')
                    ->suffix(fn (Livro $record) => ' / '.$record->quantidade_total)
                    ->color(fn (Livro $record) => $record->quantidade_disponivel > 0 ? 'success' : 'danger')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('titulo')
            ->stackedOnMobile();
    }
}
