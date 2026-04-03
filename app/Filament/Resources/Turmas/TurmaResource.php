<?php

namespace App\Filament\Resources\Turmas;

use App\Filament\Resources\Turmas\Pages\CreateTurma;
use App\Filament\Resources\Turmas\Pages\EditTurma;
use App\Filament\Resources\Turmas\Pages\ListTurmas;
use App\Filament\Resources\Turmas\RelationManagers\MatriculasRelationManager;
use App\Filament\Resources\Turmas\Schemas\TurmaForm;
use App\Filament\Resources\Turmas\Tables\TurmasTable;
use App\Models\Turma;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TurmaResource extends Resource
{
    protected static ?string $model = Turma::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        // Se o usuário for Super Admin, vê tudo (Filament Shield ou manual)
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // Verifica se qualquer uma das pessoas associadas ao usuário tem perfil prof (ID 1)
        $isProfessor = $user->pessoas()->whereHas('perfis', function ($q) {
            $q->where('perfil.id', 1);
        })->exists();

        if ($isProfessor) {
            $pessoasIds = $user->pessoas->pluck('id')->toArray();

            $query->whereHas('cronogramasAula', function ($q) use ($pessoasIds) {
                $q->whereIn('pessoa_id', $pessoasIds);
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return TurmaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TurmasTable::configure($table);
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
            'index' => ListTurmas::route('/'),
            'create' => CreateTurma::route('/create'),
            'edit' => EditTurma::route('/{record}/edit'),
        ];
    }
}
