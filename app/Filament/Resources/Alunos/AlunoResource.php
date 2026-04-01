<?php

namespace App\Filament\Resources\Alunos;

use App\Filament\Resources\Alunos\Pages\CreateAluno;
use App\Filament\Resources\Alunos\Pages\EditAluno;
use App\Filament\Resources\Alunos\Pages\ListAlunos;
use App\Filament\Resources\Alunos\Schemas\AlunoForm;
use App\Filament\Resources\Alunos\Tables\AlunosTable;
use App\Models\Pessoa;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Resources\Pessoas\RelationManagers\MatriculasRelationManager;
use Illuminate\Database\Eloquent\Builder;


class AlunoResource extends Resource
{
    protected static ?string $model = Pessoa::class;

    protected static ?string $modelLabel = 'Aluno';
    protected static ?string $pluralModelLabel = 'Alunos';

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function form(Schema $schema): Schema
    {
        return AlunoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AlunosTable::configure($table);
    }

    /**
     * Filtra a consulta para garantir que apenas pessoas com o perfil de 'Aluno' sejam listadas.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('perfis', function (Builder $query) {
                $query->where('perfil.id', 2); // Aluno
            });
    }

    public static function getRelations(): array
    {
        return [
            MatriculasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlunos::route('/'),
            'create' => CreateAluno::route('/create'),
            'edit' => EditAluno::route('/{record}/edit'),
        ];
    }
}
