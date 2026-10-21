<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes;

use App\Filament\Resources\CenarioReajustes\Pages\CreateCenarioReajuste;
use App\Filament\Resources\CenarioReajustes\Pages\EditCenarioReajuste;
use App\Filament\Resources\CenarioReajustes\Pages\ListCenarioReajustes;
use App\Filament\Resources\CenarioReajustes\Pages\ViewCenarioReajuste;
use App\Filament\Resources\CenarioReajustes\RelationManagers\ItensRelationManager;
use App\Filament\Resources\CenarioReajustes\Schemas\CenarioReajusteForm;
use App\Filament\Resources\CenarioReajustes\Tables\CenarioReajustesTable;
use App\Models\CenarioReajuste;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Reajuste de mensalidades (Onda 44): simula o novo valor por série a partir dos contratos do período de origem e,
 * aprovado, grava o preço de cada série na campanha de rematrícula.
 */
class CenarioReajusteResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'aprovar',
            'aplicar',
            'comunicar',
        ];
    }

    protected static ?string $model = CenarioReajuste::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Reajuste de Mensalidades';

    protected static ?string $modelLabel = 'Cenário de Reajuste';

    protected static ?string $pluralModelLabel = 'Reajuste de Mensalidades';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['periodoRematricula', 'planilhaLei'])->withCount('itens');
    }

    public static function form(Schema $schema): Schema
    {
        return CenarioReajusteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CenarioReajustesTable::configure($table);
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
            'index' => ListCenarioReajustes::route('/'),
            'create' => CreateCenarioReajuste::route('/create'),
            'view' => ViewCenarioReajuste::route('/{record}'),
            'edit' => EditCenarioReajuste::route('/{record}/edit'),
        ];
    }
}
