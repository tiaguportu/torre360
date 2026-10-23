<?php

declare(strict_types=1);

namespace App\Filament\Resources\CasosFrequencia;

use App\Enums\StatusCasoFrequencia;
use App\Filament\Resources\CasosFrequencia\Pages\ListCasosFrequencia;
use App\Filament\Resources\CasosFrequencia\Pages\ViewCasoFrequencia;
use App\Filament\Resources\CasosFrequencia\RelationManagers\ContatosRelationManager;
use App\Filament\Resources\CasosFrequencia\Schemas\CasoFrequenciaInfolist;
use App\Filament\Resources\CasosFrequencia\Tables\CasosFrequenciaTable;
use App\Models\CasoFrequencia;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Casos de faltas excessivas (Onda 57): da lista de alunos acima do limite ao ofício ao Conselho Tutelar, com o registro
 * das tentativas de contato com a família. Os casos nascem em "Faltas Excessivas" (a apuração); aqui se acompanha cada um.
 * Sem criação direta, edição livre nem exclusão: o histórico é a prova de que a escola agiu.
 */
class CasoFrequenciaResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'enviar',
        ];
    }

    protected static ?string $model = CasoFrequencia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Casos de Faltas';

    protected static ?string $modelLabel = 'Caso de Faltas';

    protected static ?string $pluralModelLabel = 'Casos de Faltas';

    protected static ?string $slug = 'academico/casos-de-faltas';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['matricula.pessoa', 'matricula.turma', 'conselhoTutelar', 'periodoLetivo', 'documentosAssinatura'])
            ->withCount('contatos');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('ViewAny:CasoFrequencia')) {
            return null;
        }

        $abertos = CasoFrequencia::query()->where('status', StatusCasoFrequencia::Aberto->value)->count();

        return $abertos > 0 ? (string) $abertos : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Casos de faltas ainda sem contato com a família';
    }

    public static function table(Table $table): Table
    {
        return CasosFrequenciaTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CasoFrequenciaInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            ContatosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasosFrequencia::route('/'),
            'view' => ViewCasoFrequencia::route('/{record}'),
        ];
    }
}
