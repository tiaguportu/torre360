<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicadorMetas;

use App\Filament\Resources\IndicadorMetas\Pages\CreateIndicadorMeta;
use App\Filament\Resources\IndicadorMetas\Pages\EditIndicadorMeta;
use App\Filament\Resources\IndicadorMetas\Pages\ListIndicadorMetas;
use App\Filament\Resources\IndicadorMetas\Schemas\IndicadorMetaForm;
use App\Filament\Resources\IndicadorMetas\Tables\IndicadorMetasTable;
use App\Models\IndicadorMeta;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Metas anuais dos indicadores do Painel da Direção (Onda 45).
 */
class IndicadorMetaResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete'];
    }

    protected static ?string $model = IndicadorMeta::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static UnitEnum|string|null $navigationGroup = 'Relatórios';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'meta de indicador';

    protected static ?string $pluralModelLabel = 'Metas dos indicadores';

    protected static ?string $navigationLabel = 'Metas dos Indicadores';

    protected static ?string $slug = 'painel-da-direcao/metas';

    public static function form(Schema $schema): Schema
    {
        return IndicadorMetaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndicadorMetasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndicadorMetas::route('/'),
            'create' => CreateIndicadorMeta::route('/create'),
            'edit' => EditIndicadorMeta::route('/{record}/edit'),
        ];
    }
}
