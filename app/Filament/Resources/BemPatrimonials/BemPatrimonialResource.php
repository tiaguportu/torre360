<?php

namespace App\Filament\Resources\BemPatrimonials;

use App\Filament\Resources\BemPatrimonials\Pages\CreateBemPatrimonial;
use App\Filament\Resources\BemPatrimonials\Pages\EditBemPatrimonial;
use App\Filament\Resources\BemPatrimonials\Pages\ListBemPatrimonials;
use App\Filament\Resources\BemPatrimonials\RelationManagers\MovimentacoesRelationManager;
use App\Filament\Resources\BemPatrimonials\Schemas\BemPatrimonialForm;
use App\Filament\Resources\BemPatrimonials\Tables\BemPatrimonialsTable;
use App\Models\BemPatrimonial;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BemPatrimonialResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = BemPatrimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static UnitEnum|string|null $navigationGroup = 'Patrimônio';

    protected static ?string $modelLabel = 'Bem Patrimonial';

    protected static ?string $pluralModelLabel = 'Bens Patrimoniais';

    protected static ?string $recordTitleAttribute = 'descricao';

    public static function form(Schema $schema): Schema
    {
        return BemPatrimonialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BemPatrimonialsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MovimentacoesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBemPatrimonials::route('/'),
            'create' => CreateBemPatrimonial::route('/create'),
            'edit' => EditBemPatrimonial::route('/{record}/edit'),
        ];
    }
}
