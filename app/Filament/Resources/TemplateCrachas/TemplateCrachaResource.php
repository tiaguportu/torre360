<?php

namespace App\Filament\Resources\TemplateCrachas;

use App\Filament\Resources\TemplateCrachas\Pages\CreateTemplateCracha;
use App\Filament\Resources\TemplateCrachas\Pages\EditTemplateCracha;
use App\Filament\Resources\TemplateCrachas\Pages\ListTemplateCrachas;
use App\Filament\Resources\TemplateCrachas\Schemas\TemplateCrachaForm;
use App\Filament\Resources\TemplateCrachas\Tables\TemplateCrachasTable;
use App\Models\TemplateCracha;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TemplateCrachaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = TemplateCracha::class;

    protected static ?string $modelLabel = 'Template de Crachá';

    protected static ?string $pluralModelLabel = 'Templates de Crachá';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return TemplateCrachaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemplateCrachasTable::configure($table);
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
            'index' => ListTemplateCrachas::route('/'),
            'create' => CreateTemplateCracha::route('/create'),
            'edit' => EditTemplateCracha::route('/{record}/edit'),
        ];
    }
}
