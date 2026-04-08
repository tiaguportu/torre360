<?php

namespace App\Filament\Resources\CodigoBacens;

use App\Filament\Resources\CodigoBacens\Pages\CreateCodigoBacen;
use App\Filament\Resources\CodigoBacens\Pages\EditCodigoBacen;
use App\Filament\Resources\CodigoBacens\Pages\ListCodigoBacens;
use App\Filament\Resources\CodigoBacens\Schemas\CodigoBacenForm;
use App\Filament\Resources\CodigoBacens\Tables\CodigoBacensTable;
use App\Models\CodigoBacen;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CodigoBacenResource extends Resource
{
    protected static ?string $model = CodigoBacen::class;

    protected static ?string $modelLabel = 'Código BACEN';
    protected static ?string $pluralModelLabel = 'Códigos BACEN';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    public static function form(Schema $schema): Schema
    {
        return CodigoBacenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CodigoBacensTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCodigoBacens::route('/'),
            'create' => CreateCodigoBacen::route('/create'),
            'edit' => EditCodigoBacen::route('/{record}/edit'),
        ];
    }
}
