<?php

namespace App\Filament\Resources\CronogramaAulas;

use App\Filament\Resources\CronogramaAulas\Schemas\CronogramaAulaForm;
use App\Filament\Resources\CronogramaAulas\Tables\CronogramaAulasTable;
use App\Models\CronogramaAula;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CronogramaAulaResource extends Resource implements HasShieldPermissions
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
            'lancarFrequencia',
            'verificaConflitos',
        ];
    }

    protected static ?string $model = CronogramaAula::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    public static function form(Schema $schema): Schema
    {
        return CronogramaAulaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CronogramaAulasTable::configure($table);
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
            'index' => Pages\ListCronogramaAulas::route('/'),
            'calendar' => Pages\Calendar::route('/calendar'),
            'create' => Pages\CreateCronogramaAula::route('/create'),
            'verifica-conflitos' => Pages\VerificaConflitos::route('/verifica-conflitos'),
            'edit' => Pages\EditCronogramaAula::route('/{record}/edit'),
            'lancar-frequencia' => Pages\LancarFrequencia::route('/{record}/frequencia'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user) {
            // Filtro para Professor
            if ($user->hasRole('professor')) {
                $query->where('pessoa_id', $user->pessoa?->id);
            }

            // Filtro para Responsável
            if ($user->hasRole('responsavel') && $user->pessoa) {
                $query->whereHas('turma.matriculas.contrato.responsaveisFinanceiros', function ($query) use ($user) {
                    $query->where('pessoa_id', $user->pessoa->id);
                });
            }
        }

        return $query;
    }
}
