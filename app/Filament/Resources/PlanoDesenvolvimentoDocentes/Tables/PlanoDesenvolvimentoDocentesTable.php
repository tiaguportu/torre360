<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoDesenvolvimentoDocentes\Tables;

use App\Enums\StatusPlanoDesenvolvimento;
use App\Models\PlanoDesenvolvimentoDocente;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlanoDesenvolvimentoDocentesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('funcionario.pessoa.nome')->label('Professor')->searchable()->weight('bold'),
                TextColumn::make('avaliacao.ciclo.nome')->label('Ciclo')->placeholder('—'),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('acoes_count')->label('Ações')->numeric(),
                TextColumn::make('acoes_atrasadas_count')
                    ->label('Atrasadas')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('data_revisao')->label('Revisão')->date('d/m/Y')->placeholder('—')->sortable(),
                IconColumn::make('ciencia_em')->label('Ciência')->boolean()->state(fn (PlanoDesenvolvimentoDocente $record): bool => $record->ciencia_em !== null)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(StatusPlanoDesenvolvimento::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Abrir'),
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn (PlanoDesenvolvimentoDocente $record): bool => (bool) auth()->user()?->can('update', $record)),
            ])
            ->stackedOnMobile();
    }
}
