<?php

namespace App\Filament\Resources\MaterialAulas;

use App\Filament\Resources\MaterialAulas\Pages\CreateMaterialAula;
use App\Filament\Resources\MaterialAulas\Pages\EditMaterialAula;
use App\Filament\Resources\MaterialAulas\Pages\ListMaterialAulas;
use App\Filament\Resources\MaterialAulas\Schemas\MaterialAulaForm;
use App\Filament\Resources\MaterialAulas\Tables\MaterialAulasTable;
use App\Models\MaterialAula;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MaterialAulaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = MaterialAula::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?string $modelLabel = 'Material de Aula';

    protected static ?string $pluralModelLabel = 'Materiais de Aula';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function form(Schema $schema): Schema
    {
        return MaterialAulaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaterialAulasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterialAulas::route('/'),
            'create' => CreateMaterialAula::route('/create'),
            'edit' => EditMaterialAula::route('/{record}/edit'),
        ];
    }
}
