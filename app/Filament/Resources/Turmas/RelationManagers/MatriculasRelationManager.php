<?php

namespace App\Filament\Resources\Turmas\RelationManagers;

use App\Filament\Resources\Matriculas\Schemas\MatriculaForm;
use App\Filament\Resources\Matriculas\Tables\MatriculasTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
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
        return MatriculasTable::configure($table)
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
