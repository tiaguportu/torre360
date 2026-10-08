<?php

namespace App\Filament\Resources\TransferenciasEscolares;

use App\Filament\Resources\TransferenciasEscolares\Pages\CreateTransferenciaEscolar;
use App\Filament\Resources\TransferenciasEscolares\Pages\EditTransferenciaEscolar;
use App\Filament\Resources\TransferenciasEscolares\Pages\ListTransferenciasEscolares;
use App\Filament\Resources\TransferenciasEscolares\Schemas\TransferenciaEscolarForm;
use App\Filament\Resources\TransferenciasEscolares\Tables\TransferenciasEscolaresTable;
use App\Models\TransferenciaEscolar;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TransferenciaEscolarResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = TransferenciaEscolar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?string $modelLabel = 'Transferência Escolar';

    protected static ?string $pluralModelLabel = 'Transferências Escolares';

    protected static ?string $recordTitleAttribute = 'escola_externa_nome';

    public static function form(Schema $schema): Schema
    {
        return TransferenciaEscolarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransferenciasEscolaresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferenciasEscolares::route('/'),
            'create' => CreateTransferenciaEscolar::route('/create'),
            'edit' => EditTransferenciaEscolar::route('/{record}/edit'),
        ];
    }
}
