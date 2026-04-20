<?php

namespace App\Filament\Resources\Matriculas;

use App\Filament\Resources\Matriculas\Pages\BoletimMatricula;
use App\Filament\Resources\Matriculas\Pages\CreateMatricula;
use App\Filament\Resources\Matriculas\Pages\DocumentosMatricula;
use App\Filament\Resources\Matriculas\Pages\EditarBoletimMatricula;
use App\Filament\Resources\Matriculas\Pages\EditMatricula;
use App\Filament\Resources\Matriculas\Pages\ListMatriculas;
use App\Filament\Resources\Matriculas\RelationManagers\DocumentoInseridosRelationManager;
use App\Filament\Resources\Matriculas\Schemas\MatriculaForm;
use App\Filament\Resources\Matriculas\Tables\MatriculasTable;
use App\Models\Matricula;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MatriculaResource extends Resource implements HasShieldPermissions
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
            'documentos',
            'avisarPendencia',
            'boletim',
            'boletim_editar',
        ];
    }

    protected static ?string $model = Matricula::class;

    protected static ?string $modelLabel = 'Matrícula';

    protected static ?string $pluralModelLabel = 'Matrículas';

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function form(Schema $schema): Schema
    {
        return MatriculaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MatriculasTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['turma.serie.curso.documentos', 'documentoInseridos', 'pessoa']);

        $user = auth()->user();

        // Se NÃO for Super Admin, NÃO for Admin, NÃO for Secretaria e tiver uma Pessoa vinculada,
        // filtra pelas matrículas onde a pessoa do usuário é responsável financeiro OU responsável vinculado ao aluno.
        if ($user && ! $user->hasRole(['super_admin', 'admin', 'secretaria']) && $user->pessoa) {
            $pessoaId = $user->pessoa->id;

            $query->where(function (Builder $query) use ($pessoaId) {
                $query->whereHas('contrato.responsaveisFinanceiros', function (Builder $query) use ($pessoaId) {
                    $query->where('pessoa_id', $pessoaId);
                })
                    ->orWhereHas('pessoa.responsaveis', function (Builder $query) use ($pessoaId) {
                        $query->whereKey($pessoaId);
                    });
            });
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            DocumentoInseridosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMatriculas::route('/'),
            'create' => CreateMatricula::route('/create'),
            'documentos' => DocumentosMatricula::route('/{record}/documentos'),
            'edit' => EditMatricula::route('/{record}/edit'),
            'boletim' => BoletimMatricula::route('/{record}/boletim'),
            'boletim.editar' => EditarBoletimMatricula::route('/{record}/boletim/editar'),
        ];
    }
}
