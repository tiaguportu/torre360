<?php

namespace App\Filament\Resources\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\AgendarPreceptoria;
use App\Filament\Resources\Preceptorias\Pages\CreatePreceptoria;
use App\Filament\Resources\Preceptorias\Pages\EditPreceptoria;
use App\Filament\Resources\Preceptorias\Pages\ListPreceptorias;
use App\Filament\Resources\Preceptorias\Schemas\PreceptoriaForm;
use App\Filament\Resources\Preceptorias\Tables\PreceptoriasTable;
use App\Models\Preceptoria;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PreceptoriaResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'agendar',
        ];
    }

    protected static ?string $model = Preceptoria::class;

    protected static ?string $modelLabel = 'Preceptoria';

    protected static ?string $pluralModelLabel = 'Preceptorias';

    protected static string|\UnitEnum|null $navigationGroup = 'Preceptoria';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return PreceptoriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PreceptoriasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreceptorias::route('/'),
            'create' => CreatePreceptoria::route('/create'),
            'edit' => EditPreceptoria::route('/{record}/edit'),
            'agendar' => AgendarPreceptoria::route('/agendar'),
        ];
    }
}
