<?php

namespace App\Filament\Resources\CampoExperiencias;

use App\Filament\Resources\CampoExperiencias\Pages\ManageCampoExperiencias;
use App\Filament\Resources\CampoExperiencias\Schemas\CampoExperienciaForm;
use App\Filament\Resources\CampoExperiencias\Tables\CampoExperienciasTable;
use App\Models\CampoExperiencia;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CampoExperienciaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = CampoExperiencia::class;

    protected static ?string $modelLabel = 'Campo de Experiência';

    protected static ?string $pluralModelLabel = 'Campos de Experiência';

    protected static string|\UnitEnum|null $navigationGroup = 'Currículo (BNCC)';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    public static function form(Schema $schema): Schema
    {
        return CampoExperienciaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampoExperienciasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCampoExperiencias::route('/'),
        ];
    }
}
