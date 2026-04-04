<?php

namespace App\Filament\Resources\Titulos;

use App\Filament\Resources\Concerns\HasNavigationBadge;
use App\Filament\Resources\Titulos\Pages\CreateTitulo;
use App\Filament\Resources\Titulos\Pages\EditTitulo;
use App\Filament\Resources\Titulos\Pages\ListTitulos;
use App\Filament\Resources\Titulos\Schemas\TituloForm;
use App\Filament\Resources\Titulos\Tables\TitulosTable;
use App\Models\Titulo;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TituloResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Titulo::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    public static function form(Schema $schema): Schema
    {
        return TituloForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TitulosTable::configure($table);
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
            'index' => ListTitulos::route('/'),
            'create' => CreateTitulo::route('/create'),
            'edit' => EditTitulo::route('/{record}/edit'),
        ];
    }
}
