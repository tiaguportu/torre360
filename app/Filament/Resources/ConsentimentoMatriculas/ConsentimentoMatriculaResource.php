<?php

namespace App\Filament\Resources\ConsentimentoMatriculas;

use App\Filament\Resources\ConsentimentoMatriculas\Pages\CreateConsentimentoMatricula;
use App\Filament\Resources\ConsentimentoMatriculas\Pages\EditConsentimentoMatricula;
use App\Filament\Resources\ConsentimentoMatriculas\Pages\ListConsentimentoMatriculas;
use App\Filament\Resources\ConsentimentoMatriculas\Schemas\ConsentimentoMatriculaForm;
use App\Filament\Resources\ConsentimentoMatriculas\Tables\ConsentimentoMatriculasTable;
use App\Models\ConsentimentoMatricula;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ConsentimentoMatriculaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = ConsentimentoMatricula::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?string $modelLabel = 'Consentimento';

    protected static ?string $pluralModelLabel = 'Consentimentos';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return ConsentimentoMatriculaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsentimentoMatriculasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsentimentoMatriculas::route('/'),
            'create' => CreateConsentimentoMatricula::route('/create'),
            'edit' => EditConsentimentoMatricula::route('/{record}/edit'),
        ];
    }
}
