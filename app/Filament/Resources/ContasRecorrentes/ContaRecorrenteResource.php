<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContasRecorrentes;

use App\Filament\Resources\ContasRecorrentes\Pages\CreateContaRecorrente;
use App\Filament\Resources\ContasRecorrentes\Pages\EditContaRecorrente;
use App\Filament\Resources\ContasRecorrentes\Pages\ListContasRecorrentes;
use App\Filament\Resources\ContasRecorrentes\Schemas\ContaRecorrenteForm;
use App\Filament\Resources\ContasRecorrentes\Tables\ContasRecorrentesTable;
use App\Models\ContaRecorrente;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Despesas que se repetem (Onda 78): aluguel, internet, serviços. O modelo gera as contas a pagar com antecedência.
 */
class ContaRecorrenteResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete'];
    }

    protected static ?string $model = ContaRecorrente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 24;

    protected static ?string $modelLabel = 'conta recorrente';

    protected static ?string $pluralModelLabel = 'Contas recorrentes';

    protected static ?string $navigationLabel = 'Contas Recorrentes';

    protected static ?string $slug = 'financeiro/contas-recorrentes';

    protected static ?string $recordTitleAttribute = 'descricao';

    public static function form(Schema $schema): Schema
    {
        return ContaRecorrenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContasRecorrentesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContasRecorrentes::route('/'),
            'create' => CreateContaRecorrente::route('/create'),
            'edit' => EditContaRecorrente::route('/{record}/edit'),
        ];
    }
}
