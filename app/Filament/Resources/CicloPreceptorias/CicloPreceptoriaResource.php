<?php

namespace App\Filament\Resources\CicloPreceptorias;

use App\Filament\Resources\CicloPreceptorias\Pages\CreateCicloPreceptoria;
use App\Filament\Resources\CicloPreceptorias\Pages\EditCicloPreceptoria;
use App\Filament\Resources\CicloPreceptorias\Pages\ListCicloPreceptorias;
use App\Filament\Resources\CicloPreceptorias\Schemas\CicloPreceptoriaForm;
use App\Filament\Resources\CicloPreceptorias\Tables\CicloPreceptoriasTable;
use App\Models\CicloPreceptoria;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CicloPreceptoriaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = CicloPreceptoria::class;

    protected static ?string $modelLabel = 'Ciclo de Preceptoria';

    protected static ?string $pluralModelLabel = 'Ciclos de Preceptoria';

    protected static \UnitEnum|string|null $navigationGroup = 'Preceptoria';

    protected static ?int $navigationSort = 0;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

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
        return CicloPreceptoriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CicloPreceptoriasTable::configure($table);
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
            'index' => ListCicloPreceptorias::route('/'),
            'create' => CreateCicloPreceptoria::route('/create'),
            'edit' => EditCicloPreceptoria::route('/{record}/edit'),
        ];
    }
}
