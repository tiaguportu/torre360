<?php

declare(strict_types=1);

namespace App\Filament\Resources\CicloAvaliacaoDocentes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CicloAvaliacaoDocentesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('instrumento')->withCount('avaliacoes'))
            ->defaultSort('data_inicio', 'desc')
            ->columns([
                TextColumn::make('nome')->label('Ciclo')->searchable()->sortable()->weight('bold'),
                TextColumn::make('data_inicio')->label('Início')->date('d/m/Y')->sortable(),
                TextColumn::make('data_fim')->label('Fim')->date('d/m/Y')->sortable(),
                TextColumn::make('instrumento.nome')->label('Instrumento')->sortable(),
                TextColumn::make('avaliacoes_count')->label('Avaliações')->numeric()->sortable(),
                IconColumn::make('ativo')->label('Aberto')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('ativo')->label('Situação')->trueLabel('Abertos')->falseLabel('Encerrados'),
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
