<?php

namespace App\Filament\Resources\CronogramaAulas;

use App\Filament\Resources\CronogramaAulas\Schemas\CronogramaAulaForm;
use App\Filament\Resources\CronogramaAulas\Tables\CronogramaAulasTable;
use App\Models\CronogramaAula;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Aula')
                    ->components([
                        Grid::make(3)
                            ->components([
                                TextEntry::make('periodoLetivo.nome')
                                    ->label('Período Letivo'),
                                TextEntry::make('turma.nome')
                                    ->label('Turma'),
                                TextEntry::make('disciplina.nome')
                                    ->label('Disciplina'),
                                TextEntry::make('professor.nome')
                                    ->label('Professor'),
                                TextEntry::make('data')
                                    ->label('Data')
                                    ->date('d/m/Y'),
                                TextEntry::make('hora_inicio')
                                    ->label('Hora de Início')
                                    ->time('H:i'),
                                TextEntry::make('hora_fim')
                                    ->label('Hora de Fim')
                                    ->time('H:i'),
                                TextEntry::make('conteudo_ministrado')
                                    ->label('Conteúdo Ministrado')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
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
            'view' => Pages\ViewCronogramaAula::route('/{record}'),
            'edit' => Pages\EditCronogramaAula::route('/{record}/edit'),
            'lancar-frequencia' => Pages\LancarFrequencia::route('/{record}/frequencia'),
        ];
    }

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

        // Verifica se qualquer uma das pessoas associadas ao usuário tem perfil professor (ID 1)
        $isProfessor = $user->pessoas()->whereHas('perfis', function ($q) {
            $q->where('perfil.id', 1);
        })->exists();

        if ($isProfessor) {
            $pessoasIds = $user->pessoas->pluck('id')->toArray();
            // Filtra o cronograma pelas pessoas do usuário que são professores
            $query->whereIn('pessoa_id', $pessoasIds);
        }

        // Filtro para Responsável
        $isResponsavel = $user->pessoas()->whereHas('perfis', function ($q) {
            $q->where('perfil.id', 3);
        })->exists();

        if ($isResponsavel && ! $user->hasRole('super_admin')) {
            $pessoasIds = $user->pessoas->pluck('id')->toArray();
            $query->whereHas('turma.matriculas.contrato.responsaveisFinanceiros', function ($q) use ($pessoasIds) {
                $q->whereIn('pessoa_id', $pessoasIds);
            });
        }

        return $query;
    }
}
