<?php

namespace App\Filament\Resources\Avaliacaos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AvaliacaosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('categoria.nome')
                    ->label('Categoria')
                    ->sortable(),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->sortable(),
                TextColumn::make('etapaAvaliativa.nome')
                    ->label('Etapa'),
                TextColumn::make('data_prevista')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                IconColumn::make('tem_pendencia')
                    ->label('Pendência')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
            ])
            ->filters([
                Filter::make('pendentes')
                    ->label('Pendência de Lançamento')
                    ->query(fn (Builder $query) => $query->pendentes())
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
