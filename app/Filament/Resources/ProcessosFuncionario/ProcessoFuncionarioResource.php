<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessosFuncionario;

use App\Filament\Resources\ProcessosFuncionario\Pages\ListProcessosFuncionario;
use App\Filament\Resources\ProcessosFuncionario\Pages\ViewProcessoFuncionario;
use App\Filament\Resources\ProcessosFuncionario\RelationManagers\ItensRelationManager;
use App\Filament\Resources\ProcessosFuncionario\Schemas\ProcessoFuncionarioForm;
use App\Filament\Resources\ProcessosFuncionario\Tables\ProcessosFuncionarioTable;
use App\Models\ItemProcessoFuncionario;
use App\Models\ProcessoFuncionario;
use App\Services\ProcessoFuncionarioService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Admissão e desligamento de funcionários (Onda 65): a lista de providências de cada entrada e saída, com prazo,
 * comprovante e a conclusão que, no desligamento, tira os acessos da pessoa ao sistema. Sem criação direta nem exclusão:
 * o processo nasce pela ação "Iniciar" e o histórico fica.
 */
class ProcessoFuncionarioResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'concluir',
        ];
    }

    protected static ?string $model = ProcessoFuncionario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightOnRectangle;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Admissão e Desligamento';

    protected static ?string $modelLabel = 'Processo de Admissão ou Desligamento';

    protected static ?string $pluralModelLabel = 'Admissões e Desligamentos';

    protected static ?string $slug = 'rh/admissao-desligamento';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['funcionario.pessoa'])
            ->withCount([
                'itens as itens_total',
                'itens as itens_resolvidos' => fn (Builder $query) => $query->whereNotNull('concluido_em'),
                'itens as itens_atrasados' => fn (Builder $query) => $query->whereNull('concluido_em')->whereNotNull('prazo')->whereDate('prazo', '<', today()->toDateString()),
            ]);
    }

    /**
     * Providências vencidas em processos em andamento, guardadas por 10 minutos para o menu lateral não contar a cada
     * página.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('ViewAny:ProcessoFuncionario')) {
            return null;
        }

        $total = Cache::remember(ProcessoFuncionarioService::CACHE_SELO, 600, fn (): int => ItemProcessoFuncionario::query()->atrasados()->count());

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return ProcessoFuncionarioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessosFuncionarioTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItensRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcessosFuncionario::route('/'),
            'view' => ViewProcessoFuncionario::route('/{record}'),
        ];
    }
}
