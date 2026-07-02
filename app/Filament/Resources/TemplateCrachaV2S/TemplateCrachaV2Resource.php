<?php

namespace App\Filament\Resources\TemplateCrachaV2S;

use App\Filament\Resources\TemplateCrachaV2S\Pages\CreateTemplateCrachaV2;
use App\Filament\Resources\TemplateCrachaV2S\Pages\EditTemplateCrachaV2;
use App\Filament\Resources\TemplateCrachaV2S\Pages\ListTemplateCrachaV2S;
use App\Filament\Resources\TemplateCrachaV2S\Schemas\TemplateCrachaV2Form;
use App\Filament\Resources\TemplateCrachaV2S\Tables\TemplateCrachaV2STable;
use App\Models\TemplateCrachaV2;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TemplateCrachaV2Resource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = TemplateCrachaV2::class;

    protected static ?string $modelLabel = 'Template de Crachá V2';

    protected static ?string $pluralModelLabel = 'Templates de Crachá V2';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 41;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return TemplateCrachaV2Form::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemplateCrachaV2STable::configure($table);
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
            'index' => ListTemplateCrachaV2S::route('/'),
            'create' => CreateTemplateCrachaV2::route('/create'),
            'edit' => EditTemplateCrachaV2::route('/{record}/edit'),
        ];
    }
}
