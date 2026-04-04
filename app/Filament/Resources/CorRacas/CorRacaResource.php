<?php

namespace App\Filament\Resources\CorRacas;

use App\Filament\Resources\Concerns\HasNavigationBadge;
use App\Filament\Resources\CorRacas\Pages\CreateCorRaca;
use App\Filament\Resources\CorRacas\Pages\EditCorRaca;
use App\Filament\Resources\CorRacas\Pages\ListCorRacas;
use App\Filament\Resources\CorRacas\Pages\ViewCorRaca;
use App\Filament\Resources\CorRacas\Schemas\CorRacaForm;
use App\Filament\Resources\CorRacas\Schemas\CorRacaInfolist;
use App\Filament\Resources\CorRacas\Tables\CorRacasTable;
use App\Models\CorRaca;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CorRacaResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = CorRaca::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return CorRacaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CorRacaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CorRacasTable::configure($table);
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
            'index' => ListCorRacas::route('/'),
            'create' => CreateCorRaca::route('/create'),
            'view' => ViewCorRaca::route('/{record}'),
            'edit' => EditCorRaca::route('/{record}/edit'),
        ];
    }
}
