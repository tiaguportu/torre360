<?php

namespace App\Filament\Resources\RegistroRotinaDiarias;

use App\Filament\Resources\RegistroRotinaDiarias\Pages\CreateRegistroRotinaDiaria;
use App\Filament\Resources\RegistroRotinaDiarias\Pages\EditRegistroRotinaDiaria;
use App\Filament\Resources\RegistroRotinaDiarias\Pages\ListRegistroRotinaDiarias;
use App\Filament\Resources\RegistroRotinaDiarias\Schemas\RegistroRotinaDiariaForm;
use App\Filament\Resources\RegistroRotinaDiarias\Tables\RegistroRotinaDiariasTable;
use App\Models\RegistroRotinaDiaria;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RegistroRotinaDiariaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = RegistroRotinaDiaria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?string $modelLabel = 'Rotina Diária';

    protected static ?string $pluralModelLabel = 'Agenda Diária (Educação Infantil)';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return RegistroRotinaDiariaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegistroRotinaDiariasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistroRotinaDiarias::route('/'),
            'create' => CreateRegistroRotinaDiaria::route('/create'),
            'edit' => EditRegistroRotinaDiaria::route('/{record}/edit'),
        ];
    }
}
