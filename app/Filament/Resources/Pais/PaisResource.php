<?php

namespace App\Filament\Resources\Pais;


use App\Filament\Resources\Pais\Pages\CreatePais;
use App\Filament\Resources\Pais\Pages\EditPais;
use App\Filament\Resources\Pais\Pages\ListPais;
use App\Filament\Resources\Pais\Schemas\PaisForm;
use App\Filament\Resources\Pais\Tables\PaisTable;
use App\Models\Pais;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaisResource extends Resource
{

    protected static ?string $model = Pais::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAmericas;

    public static function form(Schema $schema): Schema
    {
        return PaisForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EstadosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPais::route('/'),
            'create' => CreatePais::route('/create'),
            'edit' => EditPais::route('/{record}/edit'),
        ];
    }
}
