<?php

namespace App\Filament\Resources\Avaliacaos;

use App\Filament\Resources\Avaliacaos\Pages\CreateAvaliacao;
use App\Filament\Resources\Avaliacaos\Pages\EditAvaliacao;
use App\Filament\Resources\Avaliacaos\Pages\LancarNotas;
use App\Filament\Resources\Avaliacaos\Pages\ListAvaliacaos;
use App\Filament\Resources\Avaliacaos\Pages\ViewAvaliacao;
use App\Filament\Resources\Avaliacaos\Schemas\AvaliacaoForm;
use App\Filament\Resources\Avaliacaos\Tables\AvaliacaosTable;
use App\Models\Avaliacao;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AvaliacaoResource extends Resource implements HasShieldPermissions
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
            'lancarNotas',
        ];
    }

    protected static ?string $model = Avaliacao::class;

    protected static ?string $modelLabel = 'Avaliação';

    protected static ?string $pluralModelLabel = 'Avaliações';

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico'; // Academic choice for exams

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    public static function form(Schema $schema): Schema
    {
        return AvaliacaoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Avaliação')
                    ->components([
                        Grid::make(3)
                            ->components([
                                TextEntry::make('etapaAvaliativa.nome')
                                    ->label('Etapa Avaliativa'),
                                TextEntry::make('turma.nome')
                                    ->label('Turma'),
                                TextEntry::make('disciplina.nome')
                                    ->label('Disciplina'),
                                TextEntry::make('professor.nome')
                                    ->label('Professor'),
                                TextEntry::make('categoria.nome')
                                    ->label('Categoria'),
                                TextEntry::make('data_prevista')
                                    ->label('Data Prevista')
                                    ->date('d/m/Y'),
                                TextEntry::make('data_ocorrencia')
                                    ->label('Data de Ocorrência')
                                    ->date('d/m/Y'),
                                TextEntry::make('data_limite_lancamento')
                                    ->label('Data Limite de Lançamento')
                                    ->date('d/m/Y'),
                                TextEntry::make('nota_maxima')
                                    ->label('Nota Máxima'),
                                TextEntry::make('peso_etapa_avaliativa')
                                    ->label('Peso na Etapa'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AvaliacaosTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withCount([
                'matriculas',
                'notas as notas_count' => fn ($query) => $query->whereNotNull('valor'),
            ]);

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
            // Filtra as avaliações pelas pessoas do usuário que são professores
            $query->whereIn('professor_id', $pessoasIds);
        }

        return $query;
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
            'index' => ListAvaliacaos::route('/'),
            'create' => CreateAvaliacao::route('/create'),
            'view' => ViewAvaliacao::route('/{record}'),
            'edit' => EditAvaliacao::route('/{record}/edit'),
            'lancar-notas' => LancarNotas::route('/{record}/lancar-notas'),
        ];
    }
}
