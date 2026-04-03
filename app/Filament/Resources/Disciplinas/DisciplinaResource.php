<?php

namespace App\Filament\Resources\Disciplinas;

use App\Filament\Resources\Disciplinas\Pages\CreateDisciplina;
use App\Filament\Resources\Disciplinas\Pages\EditDisciplina;
use App\Filament\Resources\Disciplinas\Pages\ListDisciplinas;
use App\Filament\Resources\Disciplinas\Schemas\DisciplinaForm;
use App\Filament\Resources\Disciplinas\Tables\DisciplinasTable;
use App\Models\Disciplina;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DisciplinaResource extends Resource
{
    protected static ?string $model = Disciplina::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        // Se o usuário for Super Admin, vê tudo
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
        return DisciplinaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DisciplinasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDisciplinas::route('/'),
            'create' => CreateDisciplina::route('/create'),
            'edit' => EditDisciplina::route('/{record}/edit'),
        ];
    }
}
