<?php

declare(strict_types=1);

namespace App\Filament\Resources\AutoavaliacaoDocentes;

use App\Filament\Resources\AutoavaliacaoDocentes\Pages\EditAutoavaliacaoDocente;
use App\Filament\Resources\AutoavaliacaoDocentes\Pages\ListAutoavaliacaoDocentes;
use App\Filament\Resources\AutoavaliacaoDocentes\Pages\ViewAutoavaliacaoDocente;
use App\Filament\Resources\AutoavaliacaoDocentes\Schemas\AutoavaliacaoDocenteForm;
use App\Filament\Resources\AutoavaliacaoDocentes\Tables\AutoavaliacaoDocentesTable;
use App\Models\AutoavaliacaoDocente;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Autoavaliação do professor (melhoria da Onda 19): o próprio professor dá nota aos critérios do ciclo. O rascunho é
 * privado; depois de enviada, a coordenação a compara com as observações. Recortada por `visiveisPara()`.
 */
class AutoavaliacaoDocenteResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'enviar',
            'reabrir',
        ];
    }

    protected static ?string $model = AutoavaliacaoDocente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 31;

    protected static ?string $navigationLabel = 'Autoavaliação Docente';

    protected static ?string $modelLabel = 'Autoavaliação Docente';

    protected static ?string $pluralModelLabel = 'Autoavaliações Docentes';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visiveisPara(auth()->user())
            ->with(['ciclo.instrumento', 'funcionario.pessoa']);
    }

    public static function form(Schema $schema): Schema
    {
        return AutoavaliacaoDocenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutoavaliacaoDocentesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutoavaliacaoDocentes::route('/'),
            'view' => ViewAutoavaliacaoDocente::route('/{record}'),
            'edit' => EditAutoavaliacaoDocente::route('/{record}/edit'),
        ];
    }
}
