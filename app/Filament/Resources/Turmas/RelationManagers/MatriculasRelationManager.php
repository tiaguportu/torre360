<?php

namespace App\Filament\Resources\Turmas\RelationManagers;

use App\Filament\Resources\Matriculas\Schemas\MatriculaForm;
use App\Filament\Resources\Matriculas\Tables\MatriculasTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                CreateAction::make()
                    ->fillForm(fn ($livewire): array => [
                        'turma_id' => $livewire->getOwnerRecord()->id,
                        'data_matricula' => now()->format('Y-m-d'),
                    ]),
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
