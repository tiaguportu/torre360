<?php

namespace App\Filament\Resources\FrequenciaEscolars;

use App\Filament\Resources\FrequenciaEscolars\Pages\CreateFrequenciaEscolar;
use App\Filament\Resources\FrequenciaEscolars\Pages\EditFrequenciaEscolar;
use App\Filament\Resources\FrequenciaEscolars\Pages\ListFrequenciaEscolars;
use App\Filament\Resources\FrequenciaEscolars\Schemas\FrequenciaEscolarForm;
use App\Filament\Resources\FrequenciaEscolars\Tables\FrequenciaEscolarsTable;
use App\Models\FrequenciaEscolar;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FrequenciaEscolarResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = FrequenciaEscolar::class;

    protected static ?string $modelLabel = 'Frequência Escolar';

    protected static ?string $pluralModelLabel = 'Frequências Escolares';

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    public static function form(Schema $schema): Schema
    {
        return FrequenciaEscolarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FrequenciaEscolarsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFrequenciaEscolars::route('/'),
            'create' => CreateFrequenciaEscolar::route('/create'),
            'edit' => EditFrequenciaEscolar::route('/{record}/edit'),
        ];
    }
}
