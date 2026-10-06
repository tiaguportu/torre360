<?php

namespace App\Filament\Resources\AcordoInadimplencias;

use App\Filament\Resources\AcordoInadimplencias\Pages\CreateAcordoInadimplencia;
use App\Filament\Resources\AcordoInadimplencias\Pages\EditAcordoInadimplencia;
use App\Filament\Resources\AcordoInadimplencias\Pages\ListAcordoInadimplencias;
use App\Filament\Resources\AcordoInadimplencias\Schemas\AcordoInadimplenciaForm;
use App\Filament\Resources\AcordoInadimplencias\Tables\AcordoInadimplenciasTable;
use App\Models\AcordoInadimplencia;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AcordoInadimplenciaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = AcordoInadimplencia::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Acordos & Renegociação';

    protected static ?string $modelLabel = 'Acordo de Inadimplência';

    protected static ?string $pluralModelLabel = 'Acordos & Confissões de Dívida';

    protected static ?int $navigationSort = 16;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
            'aprovar',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return AcordoInadimplenciaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcordoInadimplenciasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcordoInadimplencias::route('/'),
            'create' => CreateAcordoInadimplencia::route('/create'),
            'edit' => EditAcordoInadimplencia::route('/{record}/edit'),
        ];
    }
}
