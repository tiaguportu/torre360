<?php

namespace App\Filament\Resources\InstituicaoEnsinos;

use App\Filament\Resources\InstituicaoEnsinos\Pages\CreateInstituicaoEnsino;
use App\Filament\Resources\InstituicaoEnsinos\Pages\EditInstituicaoEnsino;
use App\Filament\Resources\InstituicaoEnsinos\Pages\ListInstituicaoEnsinos;
use App\Filament\Resources\InstituicaoEnsinos\Schemas\InstituicaoEnsinoForm;
use App\Filament\Resources\InstituicaoEnsinos\Tables\InstituicaoEnsinosTable;
use App\Models\InstituicaoEnsino;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstituicaoEnsinoResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = InstituicaoEnsino::class;

    protected static ?string $modelLabel = 'Instituição de Ensino';

    protected static ?string $pluralModelLabel = 'Instituições de Ensino';

    protected static string|\UnitEnum|null $navigationGroup = 'Localização e Cadastros';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function form(Schema $schema): Schema
    {
        return InstituicaoEnsinoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstituicaoEnsinosTable::configure($table);
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
            'index' => ListInstituicaoEnsinos::route('/'),
            'create' => CreateInstituicaoEnsino::route('/create'),
            'edit' => EditInstituicaoEnsino::route('/{record}/edit'),
        ];
    }
}
