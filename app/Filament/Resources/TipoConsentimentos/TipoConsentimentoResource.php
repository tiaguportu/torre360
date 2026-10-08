<?php

namespace App\Filament\Resources\TipoConsentimentos;

use App\Filament\Resources\TipoConsentimentos\Pages\CreateTipoConsentimento;
use App\Filament\Resources\TipoConsentimentos\Pages\EditTipoConsentimento;
use App\Filament\Resources\TipoConsentimentos\Pages\ListTipoConsentimentos;
use App\Filament\Resources\TipoConsentimentos\Schemas\TipoConsentimentoForm;
use App\Filament\Resources\TipoConsentimentos\Tables\TipoConsentimentosTable;
use App\Models\TipoConsentimento;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TipoConsentimentoResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = TipoConsentimento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Configurações';

    protected static ?string $modelLabel = 'Tipo de Consentimento';

    protected static ?string $pluralModelLabel = 'Tipos de Consentimento';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return TipoConsentimentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TipoConsentimentosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTipoConsentimentos::route('/'),
            'create' => CreateTipoConsentimento::route('/create'),
            'edit' => EditTipoConsentimento::route('/{record}/edit'),
        ];
    }
}
