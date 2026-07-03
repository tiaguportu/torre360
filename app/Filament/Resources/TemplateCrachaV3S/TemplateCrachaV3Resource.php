<?php

namespace App\Filament\Resources\TemplateCrachaV3S;

use App\Filament\Resources\TemplateCrachaV3S\Pages\CreateTemplateCrachaV3;
use App\Filament\Resources\TemplateCrachaV3S\Pages\EditTemplateCrachaV3;
use App\Filament\Resources\TemplateCrachaV3S\Pages\ListTemplateCrachaV3S;
use App\Filament\Resources\TemplateCrachaV3S\Schemas\TemplateCrachaV3Form;
use App\Filament\Resources\TemplateCrachaV3S\Tables\TemplateCrachaV3STable;
use App\Models\TemplateCrachaV3;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TemplateCrachaV3Resource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = TemplateCrachaV3::class;

    protected static ?string $modelLabel = 'Template de Crachá V3';

    protected static ?string $pluralModelLabel = 'Templates de Crachá V3';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 42;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return TemplateCrachaV3Form::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemplateCrachaV3STable::configure($table);
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
            'index' => ListTemplateCrachaV3S::route('/'),
            'create' => CreateTemplateCrachaV3::route('/create'),
            'edit' => EditTemplateCrachaV3::route('/{record}/edit'),
        ];
    }
}
