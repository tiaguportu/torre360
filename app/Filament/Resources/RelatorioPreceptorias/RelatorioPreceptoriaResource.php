<?php

namespace App\Filament\Resources\RelatorioPreceptorias;

use App\Filament\Resources\RelatorioPreceptorias\Pages\CreateRelatorioPreceptoria;
use App\Filament\Resources\RelatorioPreceptorias\Pages\EditRelatorioPreceptoria;
use App\Filament\Resources\RelatorioPreceptorias\Pages\ListRelatorioPreceptorias;
use App\Filament\Resources\RelatorioPreceptorias\Schemas\RelatorioPreceptoriaForm;
use App\Filament\Resources\RelatorioPreceptorias\Tables\RelatorioPreceptoriasTable;
use App\Models\RelatorioPreceptoria;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RelatorioPreceptoriaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = RelatorioPreceptoria::class;

    protected static ?string $modelLabel = 'Relatório de Preceptoria';

    protected static ?string $pluralModelLabel = 'Relatórios de Preceptoria';

    protected static string|\UnitEnum|null $navigationGroup = 'Preceptoria';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return RelatorioPreceptoriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RelatorioPreceptoriasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(['super_admin', 'admin', 'secretaria'])) {
            return $query;
        }

        $pessoa = $user->pessoa;
        if (! $pessoa) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($pessoa) {
            // 1. Professor vê todos os relatórios das SUAS preceptorias
            $q->whereHas('preceptoria', fn ($pq) => $pq->where('professor_id', $pessoa->id))
                // 2. Outros usuários (Aluno/Responsável)
                ->orWhere(function (Builder $sub) use ($pessoa) {
                    $sub->where('publico', true)
                        ->whereHas('preceptoria.matricula', function (Builder $mq) use ($pessoa) {
                            $mq->where('pessoa_id', $pessoa->id)
                                ->orWhereIn('pessoa_id', $pessoa->alunos()->pluck('id'));

                        });
                });
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelatorioPreceptorias::route('/'),
            'create' => CreateRelatorioPreceptoria::route('/create'),
            'edit' => EditRelatorioPreceptoria::route('/{record}/edit'),
        ];
    }
}
