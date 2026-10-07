<?php

namespace App\Filament\Resources\ReguaCobrancas;

use App\Filament\Resources\ReguaCobrancas\Pages\CreateReguaCobranca;
use App\Filament\Resources\ReguaCobrancas\Pages\EditReguaCobranca;
use App\Filament\Resources\ReguaCobrancas\Pages\ListReguaCobrancas;
use App\Filament\Resources\ReguaCobrancas\Schemas\ReguaCobrancaForm;
use App\Filament\Resources\ReguaCobrancas\Tables\ReguaCobrancasTable;
use App\Models\ReguaCobranca;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReguaCobrancaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = ReguaCobranca::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Régua de Cobrança';

    protected static ?string $modelLabel = 'Régua de Cobrança';

    protected static ?string $pluralModelLabel = 'Réguas de Cobrança';

    protected static ?int $navigationSort = 3;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'execute',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ReguaCobrancaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReguaCobrancasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReguaCobrancas::route('/'),
            'create' => CreateReguaCobranca::route('/create'),
            'edit' => EditReguaCobranca::route('/{record}/edit'),
        ];
    }
}
