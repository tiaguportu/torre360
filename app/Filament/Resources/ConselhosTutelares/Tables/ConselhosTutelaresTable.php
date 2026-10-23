<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConselhosTutelares\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConselhosTutelaresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                TextColumn::make('unidade.nome')->label('Unidade')->placeholder('Todas'),
                TextColumn::make('municipio')->label('Município')->placeholder('—'),
                TextColumn::make('email')->label('E-mail')->placeholder('—')->toggleable(),
                TextColumn::make('telefone')->label('Telefone')->placeholder('—')->toggleable(),
                IconColumn::make('is_ativo')->label('Ativo')->boolean(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('unidade'))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('nome')
            ->stackedOnMobile();
    }
}
