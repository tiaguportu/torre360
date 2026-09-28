<?php

namespace App\Filament\Resources\Rematriculas;

use App\Filament\Resources\Rematriculas\Pages\EditRematricula;
use App\Filament\Resources\Rematriculas\Pages\ListRematriculas;
use App\Filament\Resources\Rematriculas\Schemas\RematriculaForm;
use App\Filament\Resources\Rematriculas\Tables\RematriculasTable;
use App\Models\Rematricula;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RematriculaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Rematricula::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?string $navigationLabel = 'Rematrículas';

    protected static ?string $modelLabel = 'Rematrícula';

    protected static ?string $pluralModelLabel = 'Rematrículas';

    protected static ?int $navigationSort = 4;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return RematriculaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RematriculasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRematriculas::route('/'),
            'edit' => EditRematricula::route('/{record}/edit'),
        ];
    }
}
