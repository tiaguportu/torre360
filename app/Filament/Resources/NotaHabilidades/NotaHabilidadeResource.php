<?php

namespace App\Filament\Resources\NotaHabilidades;

use App\Filament\Resources\NotaHabilidades\Pages\CreateNotaHabilidade;
use App\Filament\Resources\NotaHabilidades\Pages\EditNotaHabilidade;
use App\Filament\Resources\NotaHabilidades\Pages\ListNotaHabilidades;
use App\Filament\Resources\NotaHabilidades\Schemas\NotaHabilidadeForm;
use App\Filament\Resources\NotaHabilidades\Tables\NotaHabilidadesTable;
use App\Models\NotaHabilidade;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotaHabilidadeResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = NotaHabilidade::class;

    protected static ?string $modelLabel = 'Nota de Habilidade';

    protected static ?string $pluralModelLabel = 'Notas de Habilidades';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|\UnitEnum|null $navigationGroup = 'Avaliações';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->hasRole('professor')) {
            $query->whereHas('avaliacaoHabilidade', function ($q) use ($user) {
                $q->whereIn('professor_id', $user->pessoas()->pluck('pessoa.id'));
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return NotaHabilidadeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotaHabilidadesTable::configure($table);
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
            'index' => ListNotaHabilidades::route('/'),
            'create' => CreateNotaHabilidade::route('/create'),
            'edit' => EditNotaHabilidade::route('/{record}/edit'),
        ];
    }
}
