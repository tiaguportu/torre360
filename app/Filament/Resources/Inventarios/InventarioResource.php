<?php

namespace App\Filament\Resources\Inventarios;

use App\Filament\Resources\Inventarios\Pages\ConferenciaInventario;
use App\Filament\Resources\Inventarios\Pages\CreateInventario;
use App\Filament\Resources\Inventarios\Pages\ListInventarios;
use App\Filament\Resources\Inventarios\Schemas\InventarioForm;
use App\Filament\Resources\Inventarios\Tables\InventariosTable;
use App\Models\InventarioAcervo;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InventarioResource extends Resource implements HasShieldPermissions
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
        ];
    }

    protected static ?string $model = InventarioAcervo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Biblioteca';

    protected static ?string $modelLabel = 'Inventário de Acervo';

    protected static ?string $pluralModelLabel = 'Inventários de Acervo';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return InventarioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventariosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventarios::route('/'),
            'create' => CreateInventario::route('/create'),
            'conferencia' => ConferenciaInventario::route('/{record}/conferencia'),
        ];
    }
}
