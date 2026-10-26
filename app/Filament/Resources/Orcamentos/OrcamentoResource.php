<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orcamentos;

use App\Filament\Resources\Orcamentos\Pages\CreateOrcamento;
use App\Filament\Resources\Orcamentos\Pages\ListOrcamentos;
use App\Filament\Resources\Orcamentos\Pages\PlanilhaOrcamento;
use App\Filament\Resources\Orcamentos\Pages\ViewOrcamento;
use App\Filament\Resources\Orcamentos\Schemas\OrcamentoForm;
use App\Filament\Resources\Orcamentos\Schemas\OrcamentoInfolist;
use App\Filament\Resources\Orcamentos\Tables\OrcamentosTable;
use App\Models\Orcamento;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Orçamento anual (Onda 43): o previsto por conta do plano, centro de custo e mês. Compara-se com o realizado em *Orçado × Realizado*.
 */
class OrcamentoResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete'];
    }

    protected static ?string $model = Orcamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 22;

    protected static ?string $modelLabel = 'orçamento';

    protected static ?string $pluralModelLabel = 'Orçamentos';

    protected static ?string $navigationLabel = 'Orçamento Anual';

    protected static ?string $slug = 'financeiro/orcamentos';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return OrcamentoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrcamentoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrcamentosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrcamentos::route('/'),
            'create' => CreateOrcamento::route('/create'),
            'view' => ViewOrcamento::route('/{record}'),
            'planilha' => PlanilhaOrcamento::route('/{record}/planilha'),
        ];
    }
}
