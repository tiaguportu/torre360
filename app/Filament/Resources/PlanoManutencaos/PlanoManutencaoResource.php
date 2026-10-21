<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoManutencaos;

use App\Filament\Resources\PlanoManutencaos\Pages\CreatePlanoManutencao;
use App\Filament\Resources\PlanoManutencaos\Pages\EditPlanoManutencao;
use App\Filament\Resources\PlanoManutencaos\Pages\ListPlanoManutencaos;
use App\Filament\Resources\PlanoManutencaos\RelationManagers\ExecucoesRelationManager;
use App\Filament\Resources\PlanoManutencaos\Schemas\PlanoManutencaoForm;
use App\Filament\Resources\PlanoManutencaos\Tables\PlanoManutencaosTable;
use App\Models\PlanoManutencao;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Manutenção preventiva (Onda 39): planos recorrentes de bens e espaços que abrem ordens de serviço no prazo.
 */
class PlanoManutencaoResource extends Resource implements HasShieldPermissions
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
            'gerar_ordem',
            'registrar_execucao',
        ];
    }

    protected static ?string $model = PlanoManutencao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static UnitEnum|string|null $navigationGroup = 'Operacional';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'Plano de Manutenção';

    protected static ?string $pluralModelLabel = 'Manutenção Preventiva';

    protected static ?string $navigationLabel = 'Manutenção Preventiva';

    protected static ?string $recordTitleAttribute = 'titulo';

    /**
     * Quantidade de planos vencidos no menu: o que precisa de atenção hoje.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('ViewAny:PlanoManutencao')) {
            return null;
        }

        $vencidos = PlanoManutencao::query()->vencidos()->count();

        return $vencidos > 0 ? (string) $vencidos : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return PlanoManutencaoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanoManutencaosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ExecucoesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanoManutencaos::route('/'),
            'create' => CreatePlanoManutencao::route('/create'),
            'edit' => EditPlanoManutencao::route('/{record}/edit'),
        ];
    }
}
