<?php

declare(strict_types=1);

namespace App\Filament\Resources\CicloAvaliacaoDocentes;

use App\Filament\Resources\CicloAvaliacaoDocentes\Pages\CreateCicloAvaliacaoDocente;
use App\Filament\Resources\CicloAvaliacaoDocentes\Pages\EditCicloAvaliacaoDocente;
use App\Filament\Resources\CicloAvaliacaoDocentes\Pages\ListCicloAvaliacaoDocentes;
use App\Filament\Resources\CicloAvaliacaoDocentes\Schemas\CicloAvaliacaoDocenteForm;
use App\Filament\Resources\CicloAvaliacaoDocentes\Tables\CicloAvaliacaoDocentesTable;
use App\Models\CicloAvaliacaoDocente;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Ciclos de avaliação docente (ex.: "1º Semestre 2026"): o período em que a coordenação observa os professores e o
 * instrumento que vale para ele.
 */
class CicloAvaliacaoDocenteResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = CicloAvaliacaoDocente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 32;

    protected static ?string $modelLabel = 'Ciclo de Avaliação Docente';

    protected static ?string $pluralModelLabel = 'Ciclos de Avaliação Docente';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return CicloAvaliacaoDocenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CicloAvaliacaoDocentesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCicloAvaliacaoDocentes::route('/'),
            'create' => CreateCicloAvaliacaoDocente::route('/create'),
            'edit' => EditCicloAvaliacaoDocente::route('/{record}/edit'),
        ];
    }
}
