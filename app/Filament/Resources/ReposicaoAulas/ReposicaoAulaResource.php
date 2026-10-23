<?php

namespace App\Filament\Resources\ReposicaoAulas;

use App\Filament\Resources\ReposicaoAulas\Pages\CreateReposicaoAula;
use App\Filament\Resources\ReposicaoAulas\Pages\EditReposicaoAula;
use App\Filament\Resources\ReposicaoAulas\Pages\ListReposicaoAulas;
use App\Filament\Resources\ReposicaoAulas\Schemas\ReposicaoAulaForm;
use App\Filament\Resources\ReposicaoAulas\Tables\ReposicaoAulasTable;
use App\Models\ReposicaoAula;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReposicaoAulaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = ReposicaoAula::class;

    protected static ?string $modelLabel = 'Reposição de Aula';

    protected static ?string $pluralModelLabel = 'Reposições de Aulas';

    protected static string|\UnitEnum|null $navigationGroup = 'Calendário e Horários';

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function form(Schema $schema): Schema
    {
        return ReposicaoAulaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReposicaoAulasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReposicaoAulas::route('/'),
            'create' => CreateReposicaoAula::route('/create'),
            'edit' => EditReposicaoAula::route('/{record}/edit'),
        ];
    }
}
