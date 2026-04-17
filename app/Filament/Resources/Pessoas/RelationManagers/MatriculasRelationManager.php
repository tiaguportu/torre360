<?php

namespace App\Filament\Resources\Pessoas\RelationManagers;

use App\Filament\Resources\Matriculas\Schemas\MatriculaForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MatriculasRelationManager extends RelationManager
{
    protected static string $relationship = 'matriculas';

    protected static ?string $title = 'Matrículas';

    public function form(Schema $schema): Schema
    {
        return MatriculaForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Data Matrícula')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
