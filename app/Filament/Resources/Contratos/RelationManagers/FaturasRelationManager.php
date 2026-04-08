<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use App\Filament\Resources\Faturas\Schemas\FaturaForm;
use App\Filament\Resources\Faturas\Tables\FaturasTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class FaturasRelationManager extends RelationManager
{
    protected static string $relationship = 'faturas';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return FaturaForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return FaturasTable::configure($table);
    }
}
