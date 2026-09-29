<?php

namespace App\Filament\Resources\Salas;

use App\Filament\Resources\Salas\Pages\CreateSala;
use App\Filament\Resources\Salas\Pages\EditSala;
use App\Filament\Resources\Salas\Pages\ListSalas;
use App\Filament\Resources\Salas\Schemas\SalaForm;
use App\Filament\Resources\Salas\Tables\SalasTable;
use App\Models\Sala;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Ambientes físicos (salas de aula, laboratórios etc.) usados na grade
 * horária. Não confundir com o "ensalamento" de distribuição de alunos entre
 * turmas (`EnsalamentoService`), que é um recurso diferente.
 */
class SalaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Sala::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Sala';

    protected static ?string $pluralModelLabel = 'Salas';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return SalaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalas::route('/'),
            'create' => CreateSala::route('/create'),
            'edit' => EditSala::route('/{record}/edit'),
        ];
    }
}
