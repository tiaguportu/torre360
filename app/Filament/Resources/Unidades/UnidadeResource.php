<?php

namespace App\Filament\Resources\Unidades;


use App\Filament\Resources\Unidades\Pages\CreateUnidade;
use App\Filament\Resources\Unidades\Pages\EditUnidade;
use App\Filament\Resources\Unidades\Pages\ListUnidades;
use App\Filament\Resources\Unidades\Schemas\UnidadeForm;
use App\Filament\Resources\Unidades\Tables\UnidadesTable;
use App\Models\Unidade;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UnidadeResource extends Resource
{

    protected static ?string $model = Unidade::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public static function form(Schema $schema): Schema
    {
        return UnidadeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnidadesTable::configure($table);
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
            'index' => ListUnidades::route('/'),
            'create' => CreateUnidade::route('/create'),
            'edit' => EditUnidade::route('/{record}/edit'),
        ];
    }
}
