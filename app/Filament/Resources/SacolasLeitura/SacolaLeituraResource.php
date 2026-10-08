<?php

namespace App\Filament\Resources\SacolasLeitura;

use App\Filament\Resources\SacolasLeitura\Pages\CreateSacolaLeitura;
use App\Filament\Resources\SacolasLeitura\Pages\GerenciarSacolaLeitura;
use App\Filament\Resources\SacolasLeitura\Pages\ListSacolasLeitura;
use App\Filament\Resources\SacolasLeitura\Schemas\SacolaLeituraForm;
use App\Filament\Resources\SacolasLeitura\Tables\SacolasLeituraTable;
use App\Models\SacolaLeitura;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SacolaLeituraResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = SacolaLeitura::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static UnitEnum|string|null $navigationGroup = 'Biblioteca';

    protected static ?string $modelLabel = 'Sacola de Leitura';

    protected static ?string $pluralModelLabel = 'Sacolas de Leitura';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return SacolaLeituraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SacolasLeituraTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSacolasLeitura::route('/'),
            'create' => CreateSacolaLeitura::route('/create'),
            'gerenciar' => GerenciarSacolaLeitura::route('/{record}/gerenciar'),
        ];
    }
}
