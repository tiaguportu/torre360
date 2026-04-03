<?php

namespace App\Filament\Resources\Contratos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContratosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matriculas.pessoa.nome')
                    ->label('Alunos / Matrículas')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                TextColumn::make('responsaveisFinanceiros.pessoa.nome')
                    ->label('Responsáveis Financeiros')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                TextColumn::make('valor_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('data_aceite')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('aluno')
                    ->relationship('matriculas.pessoa', 'nome')
                    ->multiple()
                    ->label('Filtrar por Aluno')
                    ->searchable(),
                SelectFilter::make('responsavel')
                    ->relationship('responsaveisFinanceiros.pessoa', 'nome')
                    ->multiple()
                    ->label('Filtrar por Responsável')
                    ->searchable(),
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
