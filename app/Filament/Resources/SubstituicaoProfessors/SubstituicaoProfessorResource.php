<?php

namespace App\Filament\Resources\SubstituicaoProfessors;

use App\Filament\Resources\SubstituicaoProfessors\Pages\CreateSubstituicaoProfessor;
use App\Filament\Resources\SubstituicaoProfessors\Pages\EditSubstituicaoProfessor;
use App\Filament\Resources\SubstituicaoProfessors\Pages\ListSubstituicaoProfessors;
use App\Filament\Resources\SubstituicaoProfessors\Schemas\SubstituicaoProfessorForm;
use App\Filament\Resources\SubstituicaoProfessors\Tables\SubstituicaoProfessorsTable;
use App\Models\SubstituicaoProfessor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SubstituicaoProfessorResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = SubstituicaoProfessor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?string $modelLabel = 'Substituição de Professor';

    protected static ?string $pluralModelLabel = 'Substituições de Professor';

    protected static ?string $recordTitleAttribute = 'motivo';

    public static function form(Schema $schema): Schema
    {
        return SubstituicaoProfessorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubstituicaoProfessorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubstituicaoProfessors::route('/'),
            'create' => CreateSubstituicaoProfessor::route('/create'),
            'edit' => EditSubstituicaoProfessor::route('/{record}/edit'),
        ];
    }
}
