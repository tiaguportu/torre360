<?php

namespace App\Filament\Resources\ComunicacaoEmMassas;

use App\Filament\Resources\ComunicacaoEmMassas\Pages\CreateComunicacaoEmMassa;
use App\Filament\Resources\ComunicacaoEmMassas\Pages\EditComunicacaoEmMassa;
use App\Filament\Resources\ComunicacaoEmMassas\Pages\ListComunicacaoEmMassas;
use App\Filament\Resources\ComunicacaoEmMassas\Schemas\ComunicacaoEmMassaForm;
use App\Filament\Resources\ComunicacaoEmMassas\Tables\ComunicacaoEmMassasTable;
use App\Models\ComunicacaoEmMassa;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ComunicacaoEmMassaResource extends Resource implements HasShieldPermissions
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
            'enviar',
        ];
    }

    protected static ?string $model = ComunicacaoEmMassa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Comunicação em Massa';

    protected static ?string $pluralModelLabel = 'Comunicações em Massa';

    protected static ?string $navigationLabel = 'Comunicação em Massa';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return ComunicacaoEmMassaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComunicacaoEmMassasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComunicacaoEmMassas::route('/'),
            'create' => CreateComunicacaoEmMassa::route('/create'),
            'edit' => EditComunicacaoEmMassa::route('/{record}/edit'),
        ];
    }
}
