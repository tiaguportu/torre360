<?php

namespace App\Filament\Resources\Pessoas;

use App\Filament\Resources\Pessoas\Pages\CreatePessoa;
use App\Filament\Resources\Pessoas\Pages\EditPessoa;
use App\Filament\Resources\Pessoas\Pages\ListPessoas;
use App\Filament\Resources\Pessoas\RelationManagers\EnderecoRelationManager;
use App\Filament\Resources\Pessoas\RelationManagers\MatriculasRelationManager;
use App\Filament\Resources\Pessoas\Schemas\PessoaForm;
use App\Filament\Resources\Pessoas\Tables\PessoasTable;
use App\Models\Pessoa;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PessoaResource extends Resource
{
    protected static ?string $model = Pessoa::class;

    protected static ?string $modelLabel = 'Pessoa';

    protected static ?string $pluralModelLabel = 'Pessoas';

    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return PessoaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PessoasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EnderecoRelationManager::class,
            MatriculasRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->hasRole('super_admin')) {
            return $query;
        }

        if ($user->hasAnyRole(['professor', 'responsavel'])) {
            $query->where(function (Builder $query) use ($user) {
                // Pessoa diretamente vinculada ao usuário
                $query->whereHas('users', fn (Builder $q) => $q->where('users.id', $user->id));

                // Se for responsável, também vê as pessoas (alunos) vinculadas financeiramente
                if ($user->hasRole('responsavel')) {
                    $pessoasIds = $user->pessoas->pluck('id')->toArray();
                    $query->orWhereHas('matriculas.contrato.responsaveisFinanceiros', function (Builder $q) use ($pessoasIds) {
                        $q->whereIn('pessoa_id', $pessoasIds);
                    });
                }
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPessoas::route('/'),
            'create' => CreatePessoa::route('/create'),
            'edit' => EditPessoa::route('/{record}/edit'),
        ];
    }
}
