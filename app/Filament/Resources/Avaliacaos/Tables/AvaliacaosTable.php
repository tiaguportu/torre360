<?php

namespace App\Filament\Resources\Avaliacaos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AvaliacaosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('etapaAvaliativa.nome')
                    ->label('Etapa')
                    ->sortable(),

                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('professor.nome')
                    ->label('Professor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('data_prevista')
                    ->label('Data Prevista')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('nota_maxima')
                    ->label('MÁX')
                    ->numeric(2)
                    ->sortable(),

                TextColumn::make('peso_etapa_avaliativa')
                    ->label('Peso')
                    ->numeric(2)
                    ->sortable(),
            ])
            ->filters([
                //
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
