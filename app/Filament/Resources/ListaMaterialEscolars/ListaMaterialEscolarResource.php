<?php

declare(strict_types=1);

namespace App\Filament\Resources\ListaMaterialEscolars;

use App\Filament\Resources\ListaMaterialEscolars\Pages\CreateListaMaterialEscolar;
use App\Filament\Resources\ListaMaterialEscolars\Pages\EditListaMaterialEscolar;
use App\Filament\Resources\ListaMaterialEscolars\Pages\ListListaMaterialEscolars;
use App\Filament\Resources\ListaMaterialEscolars\RelationManagers\ItensRelationManager;
use App\Filament\Resources\ListaMaterialEscolars\Schemas\ListaMaterialEscolarForm;
use App\Filament\Resources\ListaMaterialEscolars\Tables\ListaMaterialEscolarsTable;
use App\Models\ListaMaterialEscolar;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Lista de material escolar por série e período (Onda 59): a secretaria monta com a trava de itens de uso coletivo
 * (Lei nº 9.870/1999, art. 1º, § 7º), publica, e as famílias veem no Portal.
 */
class ListaMaterialEscolarResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'publicar',
        ];
    }

    protected static ?string $model = ListaMaterialEscolar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 62;

    protected static ?string $navigationLabel = 'Lista de Material';

    protected static ?string $modelLabel = 'Lista de Material Escolar';

    protected static ?string $pluralModelLabel = 'Listas de Material Escolar';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['serie.curso', 'periodoLetivo'])->withCount('itens');
    }

    public static function form(Schema $schema): Schema
    {
        return ListaMaterialEscolarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ListaMaterialEscolarsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItensRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListaMaterialEscolars::route('/'),
            'create' => CreateListaMaterialEscolar::route('/create'),
            'edit' => EditListaMaterialEscolar::route('/{record}/edit'),
        ];
    }
}
