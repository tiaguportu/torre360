<?php

declare(strict_types=1);

namespace App\Filament\Resources\AvaliacaoDocentes;

use App\Filament\Resources\AvaliacaoDocentes\Pages\CreateAvaliacaoDocente;
use App\Filament\Resources\AvaliacaoDocentes\Pages\EditAvaliacaoDocente;
use App\Filament\Resources\AvaliacaoDocentes\Pages\ListAvaliacaoDocentes;
use App\Filament\Resources\AvaliacaoDocentes\Pages\ViewAvaliacaoDocente;
use App\Filament\Resources\AvaliacaoDocentes\Schemas\AvaliacaoDocenteForm;
use App\Filament\Resources\AvaliacaoDocentes\Tables\AvaliacaoDocentesTable;
use App\Models\AvaliacaoDocente;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Avaliações de desempenho docente (Onda 19): observação da coordenação sobre o professor, com nota por critério,
 * pontuação consolidada e parecer. Dado sensível: a consulta é recortada por `AvaliacaoDocente::visiveisPara()`.
 */
class AvaliacaoDocenteResource extends Resource implements HasShieldPermissions
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
            'finalizar',
            'reabrir',
        ];
    }

    protected static ?string $model = AvaliacaoDocente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Avaliações de Desempenho';

    protected static ?string $modelLabel = 'Avaliação de Desempenho Docente';

    protected static ?string $pluralModelLabel = 'Avaliações de Desempenho Docente';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visiveisPara(auth()->user())
            ->with(['ciclo', 'funcionario.pessoa', 'avaliador']);
    }

    public static function form(Schema $schema): Schema
    {
        return AvaliacaoDocenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AvaliacaoDocentesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAvaliacaoDocentes::route('/'),
            'create' => CreateAvaliacaoDocente::route('/create'),
            'view' => ViewAvaliacaoDocente::route('/{record}'),
            'edit' => EditAvaliacaoDocente::route('/{record}/edit'),
        ];
    }
}
