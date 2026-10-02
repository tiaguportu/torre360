<?php

namespace App\Filament\Resources\Funcionarios;

use App\Filament\Resources\Funcionarios\Pages\CreateFuncionario;
use App\Filament\Resources\Funcionarios\Pages\EditFuncionario;
use App\Filament\Resources\Funcionarios\Pages\ListFuncionarios;
use App\Filament\Resources\Funcionarios\RelationManagers\ContratosTrabalhoRelationManager;
use App\Filament\Resources\Funcionarios\RelationManagers\PeriodosFeriasRelationManager;
use App\Filament\Resources\Funcionarios\Schemas\FuncionarioForm;
use App\Filament\Resources\Funcionarios\Tables\FuncionariosTable;
use App\Models\Funcionario;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FuncionarioResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Funcionario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?string $modelLabel = 'Funcionário';

    protected static ?string $pluralModelLabel = 'Funcionários';

    protected static ?string $recordTitleAttribute = 'cargo';

    public static function form(Schema $schema): Schema
    {
        return FuncionarioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FuncionariosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ContratosTrabalhoRelationManager::class,
            PeriodosFeriasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFuncionarios::route('/'),
            'create' => CreateFuncionario::route('/create'),
            'edit' => EditFuncionario::route('/{record}/edit'),
        ];
    }
}
