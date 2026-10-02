<?php

namespace App\Filament\Resources\TipoBolsas;

use App\Filament\Resources\TipoBolsas\Pages\CreateTipoBolsa;
use App\Filament\Resources\TipoBolsas\Pages\EditTipoBolsa;
use App\Filament\Resources\TipoBolsas\Pages\ListTipoBolsas;
use App\Filament\Resources\TipoBolsas\Schemas\TipoBolsaForm;
use App\Filament\Resources\TipoBolsas\Tables\TipoBolsasTable;
use App\Models\TipoBolsa;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TipoBolsaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = TipoBolsa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Tipo de Bolsa';

    protected static ?string $pluralModelLabel = 'Tipos de Bolsa';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return TipoBolsaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TipoBolsasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTipoBolsas::route('/'),
            'create' => CreateTipoBolsa::route('/create'),
            'edit' => EditTipoBolsa::route('/{record}/edit'),
        ];
    }
}
