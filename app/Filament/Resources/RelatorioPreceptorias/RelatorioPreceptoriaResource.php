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
        $query = parent::getEloquentQuery();
        $user = auth()->user();

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

        return $query->whereHas('preceptoria', function (Builder $q) use ($pessoa) {
            // Professor vê seus relatórios (mesmo os não públicos)
            $q->where('professor_id', $pessoa->id)
                ->orWhere(function (Builder $sub) use ($pessoa) {
                    // Outros usuários (Alunos/Responsáveis) só veem se for PÚBLICO
                    $sub->whereHas('relatorio', fn ($qr) => $qr->where('publico', true));

                    // E se for da matrícula deles/filhos
                    $sub->where(function (Builder $inner) use ($pessoa) {
                        $inner->whereHas('matricula', function (Builder $m) use ($pessoa) {
                            $m->where('pessoa_id', $pessoa->id) // O próprio aluno
                                ->orWhereIn('pessoa_id', $pessoa->alunos()->pluck('pessoa.id')); // Ou dependentes do responsável
                        });
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
