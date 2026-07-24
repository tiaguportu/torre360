<?php

namespace App\Filament\Resources\Pessoas;

use App\Filament\Resources\Pessoas\Pages\CreatePessoa;
use App\Filament\Resources\Pessoas\Pages\EditPessoa;
use App\Filament\Resources\Pessoas\Pages\ListPessoas;
use App\Filament\Resources\Pessoas\RelationManagers\AlunosRelationManager;
use App\Filament\Resources\Pessoas\RelationManagers\EnderecoRelationManager;
use App\Filament\Resources\Pessoas\RelationManagers\MatriculasRelationManager;
use App\Filament\Resources\Pessoas\RelationManagers\NecessidadesEducacaoEspecialRelationManager;
use App\Filament\Resources\Pessoas\RelationManagers\RecursosAcessibilidadeRelationManager;
use App\Filament\Resources\Pessoas\RelationManagers\ResponsaveisRelationManager;
use App\Filament\Resources\Pessoas\RelationManagers\TranstornosAprendizagemRelationManager;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

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
            ResponsaveisRelationManager::class,
            AlunosRelationManager::class,
            EnderecoRelationManager::class,
            MatriculasRelationManager::class,
            NecessidadesEducacaoEspecialRelationManager::class,
            TranstornosAprendizagemRelationManager::class,
            RecursosAcessibilidadeRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->hasRole('super_admin')) {
            return $query;
        }

        $activeRole = session('active_role');

        // Se estiver atuando como admin ou secretaria, vê tudo
        if (in_array($activeRole, ['admin', 'secretaria'])) {
            return $query;
        }

        if (in_array($activeRole, ['professor', 'responsavel', 'aluno'])) {
            $query->where(function (Builder $query) use ($user, $activeRole) {
                // Pessoa diretamente vinculada ao usuário (ele mesmo)
                $query->whereHas('users', fn (Builder $q) => $q->where('users.id', $user->id));

                $pessoasIds = $user->pessoas->pluck('id')->toArray();

                // Se estiver atuando como responsável, vê as pessoas vinculadas a ele como alunos ou via financeiro
                if ($activeRole === 'responsavel') {
                    // Ver alunos onde ele é o responsável (tabela aluno_responsavel)
                    $query->orWhereHas('responsaveis', function (Builder $q) use ($pessoasIds) {
                        $q->whereIn('responsavel_id', $pessoasIds);
                    });

                    // Ver alunos onde ele é o responsável financeiro (via contrato)
                    $query->orWhereHas('matriculas.contrato.responsaveisFinanceiros', function (Builder $q) use ($pessoasIds) {
                        $q->whereIn('pessoa_id', $pessoasIds);
                    });
                }

                // Se estiver atuando como aluno, vê seus responsáveis (tabela aluno_responsavel e financeiro)
                if ($activeRole === 'aluno') {
                    // Ver seus responsáveis diretos
                    $query->orWhereHas('alunos', function (Builder $q) use ($pessoasIds) {
                        $q->whereIn('aluno_id', $pessoasIds);
                    });

                    // Ver quem paga suas contas (responsáveis financeiros do seu contrato)
                    $query->orWhereHas('responsaveisFinanceiros.contrato.matriculas', function (Builder $q) use ($pessoasIds) {
                        $q->whereIn('pessoa_id', $pessoasIds);
                    });
                }

                // Se estiver atuando como professor, vê seus alunos
                if ($activeRole === 'professor') {
                    // Ver alunos de turmas onde ele é o conselheiro
                    $query->orWhereHas('matriculas.turma', function (Builder $q) use ($pessoasIds) {
                        $q->whereIn('professor_conselheiro_id', $pessoasIds);
                    });

                    // Ver alunos de turmas onde ele leciona (cronograma)
                    $query->orWhereHas('matriculas.turma.cronogramasAula', function (Builder $q) use ($pessoasIds) {
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
