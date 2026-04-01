<?php

namespace App\Filament\Resources\Matriculas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\Matricula;

class MatriculasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(fn (\App\Models\Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'bg-danger-500/10 dark:bg-danger-500/20' : null)
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable()
                    ->weight(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'bold' : null)
                    ->color(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'danger' : null),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('situacaoMatricula.nome')
                    ->label('Situação')
                    ->sortable(),
                TextColumn::make('data_matricula')
                    ->label('Data')
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
