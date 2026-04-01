<?php

namespace App\Filament\Resources\PeriodoLetivos\RelationManagers;

use App\Filament\Resources\DiaNaoLetivos\Schemas\DiaNaoLetivoForm;
use App\Filament\Resources\DiaNaoLetivos\Tables\DiaNaoLetivosTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class DiaNaoLetivosRelationManager extends RelationManager
{
    protected static string $relationship = 'diasNaoLetivos';

    protected static ?string $title = 'Dias Não Letivos';

    protected static ?string $recordTitleAttribute = 'descricao';

    public function form(Schema $schema): Schema
    {
        return DiaNaoLetivoForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DiaNaoLetivosTable::configure($table)
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
