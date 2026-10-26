<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItensEstoque;

use App\Filament\Resources\ItensEstoque\Pages\CreateItemEstoque;
use App\Filament\Resources\ItensEstoque\Pages\EditItemEstoque;
use App\Filament\Resources\ItensEstoque\Pages\ListItensEstoque;
use App\Filament\Resources\ItensEstoque\RelationManagers\MovimentacoesRelationManager;
use App\Filament\Resources\ItensEstoque\Schemas\ItemEstoqueForm;
use App\Filament\Resources\ItensEstoque\Tables\ItensEstoqueTable;
use App\Models\ItemEstoque;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Estoque de consumíveis (Onda 16, fase 2): cadastro dos itens e as movimentações de entrada, saída e ajuste.
 */
class ItemEstoqueResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete'];
    }

    protected static ?string $model = ItemEstoque::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static UnitEnum|string|null $navigationGroup = 'Suprimentos';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'item de estoque';

    protected static ?string $pluralModelLabel = 'Itens de estoque';

    protected static ?string $navigationLabel = 'Estoque';

    protected static ?string $slug = 'suprimentos/estoque';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return ItemEstoqueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItensEstoqueTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [MovimentacoesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItensEstoque::route('/'),
            'create' => CreateItemEstoque::route('/create'),
            'edit' => EditItemEstoque::route('/{record}/edit'),
        ];
    }
}
