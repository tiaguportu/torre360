<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicacaoInteressados;

use App\Filament\Resources\IndicacaoInteressados\Pages\CreateIndicacaoInteressado;
use App\Filament\Resources\IndicacaoInteressados\Pages\EditIndicacaoInteressado;
use App\Filament\Resources\IndicacaoInteressados\Pages\ListIndicacaoInteressados;
use App\Filament\Resources\IndicacaoInteressados\Schemas\IndicacaoInteressadoForm;
use App\Filament\Resources\IndicacaoInteressados\Tables\IndicacaoInteressadosTable;
use App\Models\IndicacaoInteressado;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class IndicacaoInteressadoResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = IndicacaoInteressado::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Indicação (MGM)';

    protected static ?string $pluralModelLabel = 'Programa Família Indica Família';

    protected static ?string $navigationLabel = 'Família Indica Família';

    public static function form(Schema $schema): Schema
    {
        return IndicacaoInteressadoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndicacaoInteressadosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndicacaoInteressados::route('/'),
            'create' => CreateIndicacaoInteressado::route('/create'),
            'edit' => EditIndicacaoInteressado::route('/{record}/edit'),
        ];
    }
}
