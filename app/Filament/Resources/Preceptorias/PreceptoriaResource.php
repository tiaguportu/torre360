<?php

namespace App\Filament\Resources\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\AgendarPreceptoria;
use App\Filament\Resources\Preceptorias\Pages\CreatePreceptoria;
use App\Filament\Resources\Preceptorias\Pages\EditPreceptoria;
use App\Filament\Resources\Preceptorias\Pages\ListPreceptorias;
use App\Filament\Resources\Preceptorias\Pages\ViewPreceptoria;
use App\Filament\Resources\Preceptorias\Schemas\PreceptoriaForm;
use App\Filament\Resources\Preceptorias\Tables\PreceptoriasTable;
use App\Models\Preceptoria;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PreceptoriaResource extends Resource implements HasShieldPermissions
{
    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
            NavigationItem::make('Agendar Preceptoria')
                ->group(static::getNavigationGroup())
                ->icon('heroicon-o-calendar-days')
                ->activeIcon('heroicon-s-calendar-days')
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.agendar'))
                ->sort(static::getNavigationSort() + 1)
                ->url(static::getUrl('agendar'))
                ->visible(fn () => auth()->user()?->can('Agendar:Preceptoria')),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $activeRole = session('active_role');

        // Se o usuário tem papel de gestão e NÃO está atuando como um papel restritivo, vê tudo.
        // Se o active_role for de gestão, vê tudo.
        if (in_array($activeRole, ['super_admin', 'admin', 'secretaria'])) {
            return $query;
        }

        $pessoa = $user->pessoa;
        if (! $pessoa) {
            // Se não tem pessoa vinculada e não é papel de gestão, não vê nada.
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($pessoa, $activeRole) {
            $hasFilter = false;

            // 1. Professor vê suas preceptorias
            if ($activeRole === 'professor') {
                $q->where('professor_id', $pessoa->id);
                $hasFilter = true;
            }

            // 2. Aluno vê agendamentos de sua própria pessoa
            if ($activeRole === 'aluno') {
                $method = $hasFilter ? 'orWhere' : 'where';
                $q->$method(function (Builder $sub) use ($pessoa) {
                    $sub->whereHas('matricula', function (Builder $mq) use ($pessoa) {
                        $mq->where('pessoa_id', $pessoa->id);
                    });
                });
                $hasFilter = true;
            }

            // 3. Responsável vê agendamentos dos seus filhos (dependentes)
            if ($activeRole === 'responsavel') {
                $method = $hasFilter ? 'orWhere' : 'where';
                $q->$method(function (Builder $sub) use ($pessoa) {
                    $alunoIds = $pessoa->alunos()->pluck('aluno_id')->toArray();

                    $sub->whereHas('matricula', function (Builder $mq) use ($alunoIds) {
                        $mq->whereIn('pessoa_id', $alunoIds);
                    });
                });
                $hasFilter = true;
            }

            if (! $hasFilter) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'agendar',
        ];
    }

    protected static ?string $model = Preceptoria::class;

    protected static ?string $modelLabel = 'Preceptoria';

    protected static ?string $pluralModelLabel = 'Preceptorias';

    protected static string|\UnitEnum|null $navigationGroup = 'Preceptoria';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return PreceptoriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PreceptoriasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreceptorias::route('/'),
            'create' => CreatePreceptoria::route('/create'),
            'agendar' => AgendarPreceptoria::route('/agendar'),
            'view' => ViewPreceptoria::route('/{record}'),
            'edit' => EditPreceptoria::route('/{record}/edit'),
        ];
    }
}
