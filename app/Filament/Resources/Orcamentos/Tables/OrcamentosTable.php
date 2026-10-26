<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orcamentos\Tables;

use App\Enums\StatusOrcamento;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrcamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ano')->label('Ano')->sortable(),
                TextColumn::make('nome')->label('Orçamento')->searchable()->weight('bold'),
                TextColumn::make('versao')->label('Versão')->prefix('v')->sortable(),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('linhas_count')->label('Linhas')->counts('linhas')->numeric(),
                TextColumn::make('aprovado_em')->label('Aprovado em')->dateTime('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(StatusOrcamento::class),
            ])
            ->recordActions([ViewAction::make()->label('Abrir')])
            ->defaultSort('ano', 'desc')
            ->stackedOnMobile();
    }
}
