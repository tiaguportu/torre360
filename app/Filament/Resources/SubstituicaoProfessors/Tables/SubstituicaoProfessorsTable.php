<?php

namespace App\Filament\Resources\SubstituicaoProfessors\Tables;

use App\Models\SubstituicaoProfessor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubstituicaoProfessorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->placeholder('Todas'),
                TextColumn::make('professorTitular.nome')
                    ->label('Titular')
                    ->searchable(),
                TextColumn::make('professorSubstituto.nome')
                    ->label('Substituto')
                    ->searchable(),
                TextColumn::make('data_inicio')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('data_fim')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->placeholder('Em aberto')
                    ->color(fn (SubstituicaoProfessor $record) => $record->isAtiva() ? 'warning' : null),
            ])
            ->filters([
                SelectFilter::make('turma_id')
                    ->label('Turma')
                    ->relationship('turma', 'nome')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data_inicio', 'desc')
            ->stackedOnMobile();
    }
}
