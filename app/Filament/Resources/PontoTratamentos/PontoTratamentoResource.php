<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoTratamentos;

use App\Filament\Resources\PontoTratamentos\Pages\CreatePontoTratamento;
use App\Filament\Resources\PontoTratamentos\Pages\ListPontoTratamentos;
use App\Filament\Resources\PontoTratamentos\Schemas\PontoTratamentoForm;
use App\Filament\Resources\PontoTratamentos\Tables\PontoTratamentosTable;
use App\Models\PontoTratamento;
use App\Support\EscopoProfessor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Ajustes de ponto (marcação esquecida, marcação indevida, abono). Cada trabalhador pede e acompanha os próprios; a
 * administração vê todos e decide. O registro original da marcação nunca é alterado.
 */
class PontoTratamentoResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'aprovar',
        ];
    }

    protected static ?string $model = PontoTratamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 23;

    protected static ?string $modelLabel = 'Ajuste de Ponto';

    protected static ?string $pluralModelLabel = 'Ajustes de Ponto';

    protected static ?string $navigationLabel = 'Ajustes de Ponto';

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('Aprovar:PontoTratamento')) {
            return null;
        }

        $pendentes = PontoTratamento::query()->where('status', 'pendente')->count();

        return $pendentes > 0 ? (string) $pendentes : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['funcionario.pessoa', 'solicitadoPor']);

        // Quem não decide só enxerga os próprios ajustes.
        if (! auth()->user()?->can('Aprovar:PontoTratamento')) {
            $query->whereHas('funcionario', fn (Builder $funcionarios) => $funcionarios->whereIn('pessoa_id', EscopoProfessor::pessoaIds(auth()->user()) ?: [0]));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return PontoTratamentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PontoTratamentosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPontoTratamentos::route('/'),
            'create' => CreatePontoTratamento::route('/create'),
        ];
    }
}
