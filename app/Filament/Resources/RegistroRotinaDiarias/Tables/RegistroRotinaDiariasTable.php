<?php

namespace App\Filament\Resources\RegistroRotinaDiarias\Tables;

use App\Enums\HumorCrianca;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistroRotinaDiariasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable(),
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('humor')
                    ->label('Humor')
                    ->badge(),
                TextColumn::make('refeicoes_count')
                    ->label('Refeições')
                    ->counts('refeicoes'),
            ])
            ->filters([
                SelectFilter::make('turma_id')
                    ->label('Turma')
                    ->relationship('turma', 'nome')
                    ->searchable(),
                SelectFilter::make('humor')
                    ->label('Humor')
                    ->options(HumorCrianca::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data', 'desc')
            ->stackedOnMobile();
    }
}
