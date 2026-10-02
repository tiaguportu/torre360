<?php

namespace App\Filament\Resources\Emprestimos;

use App\Filament\Resources\Emprestimos\Pages\CreateEmprestimo;
use App\Filament\Resources\Emprestimos\Pages\ListEmprestimos;
use App\Filament\Resources\Emprestimos\Schemas\EmprestimoForm;
use App\Filament\Resources\Emprestimos\Tables\EmprestimosTable;
use App\Models\Emprestimo;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmprestimoResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Emprestimo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static UnitEnum|string|null $navigationGroup = 'Biblioteca';

    protected static ?string $modelLabel = 'Empréstimo';

    protected static ?string $pluralModelLabel = 'Empréstimos';

    public static function form(Schema $schema): Schema
    {
        return EmprestimoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmprestimosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmprestimos::route('/'),
            'create' => CreateEmprestimo::route('/create'),
        ];
    }
}
