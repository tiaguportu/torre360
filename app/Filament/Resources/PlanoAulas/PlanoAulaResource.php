<?php

namespace App\Filament\Resources\PlanoAulas;

use App\Filament\Resources\PlanoAulas\Pages\CreatePlanoAula;
use App\Filament\Resources\PlanoAulas\Pages\EditPlanoAula;
use App\Filament\Resources\PlanoAulas\Pages\ListPlanoAulas;
use App\Filament\Resources\PlanoAulas\Schemas\PlanoAulaForm;
use App\Filament\Resources\PlanoAulas\Tables\PlanoAulasTable;
use App\Models\PlanoAula;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * O que o professor planeja lecionar. Reaproveita o mesmo escopo por
 * professor de `CronogramaAulaResource` (turma cujo professor conselheiro ou
 * vínculo em `turma_disciplina` seja o usuário logado).
 */
class PlanoAulaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = PlanoAula::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Plano de Aula';

    protected static ?string $pluralModelLabel = 'Planos de Aula';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if (session('active_role') === 'professor') {
            $pessoasIds = array_filter(array_merge(
                [$user->pessoa?->id],
                $user->pessoas ? $user->pessoas->pluck('id')->toArray() : []
            ));

            $query->where(function (Builder $q) use ($pessoasIds) {
                $q->whereIn('professor_id', $pessoasIds)
                    ->orWhereHas('turma', function (Builder $tq) use ($pessoasIds) {
                        $tq->whereIn('professor_conselheiro_id', $pessoasIds)
                            ->orWhereHas('disciplinas', function (Builder $dq) use ($pessoasIds) {
                                $dq->whereIn('turma_disciplina.professor_id', $pessoasIds);
                            });
                    });
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return PlanoAulaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanoAulasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanoAulas::route('/'),
            'create' => CreatePlanoAula::route('/create'),
            'edit' => EditPlanoAula::route('/{record}/edit'),
        ];
    }
}
