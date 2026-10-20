<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstrumentoAvaliacaoDocentes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InstrumentoAvaliacaoDocentesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['criterios', 'ciclos']))
            ->defaultSort('nome')
            ->columns([
                TextColumn::make('nome')->label('Instrumento')->searchable()->sortable()->weight('bold'),
                TextColumn::make('escala_maxima')->label('Escala')->formatStateUsing(fn ($state): string => "1 a {$state}"),
                TextColumn::make('criterios_count')->label('Critérios')->numeric()->sortable(),
                TextColumn::make('ciclos_count')->label('Ciclos')->numeric()->sortable(),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('ativo')->label('Situação'),
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
