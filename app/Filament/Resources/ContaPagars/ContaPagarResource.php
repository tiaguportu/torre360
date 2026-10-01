<?php

namespace App\Filament\Resources\ContaPagars;

use App\Filament\Resources\ContaPagars\Pages\CreateContaPagar;
use App\Filament\Resources\ContaPagars\Pages\EditContaPagar;
use App\Filament\Resources\ContaPagars\Pages\ListContaPagars;
use App\Filament\Resources\ContaPagars\Schemas\ContaPagarForm;
use App\Filament\Resources\ContaPagars\Tables\ContaPagarsTable;
use App\Models\ContaPagar;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ContaPagarResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = ContaPagar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingDown;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Conta a Pagar';

    protected static ?string $pluralModelLabel = 'Contas a Pagar';

    protected static ?string $recordTitleAttribute = 'descricao';

    public static function form(Schema $schema): Schema
    {
        return ContaPagarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContaPagarsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContaPagars::route('/'),
            'create' => CreateContaPagar::route('/create'),
            'edit' => EditContaPagar::route('/{record}/edit'),
        ];
    }
}
