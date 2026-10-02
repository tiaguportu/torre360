<?php

namespace App\Filament\Resources\BolsaConcedidas;

use App\Filament\Resources\BolsaConcedidas\Pages\CreateBolsaConcedida;
use App\Filament\Resources\BolsaConcedidas\Pages\EditBolsaConcedida;
use App\Filament\Resources\BolsaConcedidas\Pages\ListBolsaConcedidas;
use App\Filament\Resources\BolsaConcedidas\Schemas\BolsaConcedidaForm;
use App\Filament\Resources\BolsaConcedidas\Tables\BolsaConcedidasTable;
use App\Models\BolsaConcedida;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BolsaConcedidaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = BolsaConcedida::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Bolsa Concedida';

    protected static ?string $pluralModelLabel = 'Bolsas Concedidas';

    public static function form(Schema $schema): Schema
    {
        return BolsaConcedidaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BolsaConcedidasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBolsaConcedidas::route('/'),
            'create' => CreateBolsaConcedida::route('/create'),
            'edit' => EditBolsaConcedida::route('/{record}/edit'),
        ];
    }
}
