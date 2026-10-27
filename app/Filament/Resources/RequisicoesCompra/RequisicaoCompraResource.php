<?php

declare(strict_types=1);

namespace App\Filament\Resources\RequisicoesCompra;

use App\Filament\Resources\RequisicoesCompra\Pages\CreateRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\Pages\EditRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\Pages\ListRequisicoesCompra;
use App\Filament\Resources\RequisicoesCompra\Pages\ViewRequisicaoCompra;
use App\Filament\Resources\RequisicoesCompra\Schemas\RequisicaoCompraForm;
use App\Filament\Resources\RequisicoesCompra\Schemas\RequisicaoCompraInfolist;
use App\Filament\Resources\RequisicoesCompra\Tables\RequisicoesCompraTable;
use App\Models\RequisicaoCompra;
use App\Services\ComprasService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Compras e cotações (Onda 38): do pedido do setor ao recebimento com a nota e a conta a pagar. Cada pessoa vê as próprias requisições e as que
 * precisam dela (cotar, aprovar na sua alçada, receber); `VerTodas:RequisicaoCompra` abre as dos outros setores.
 */
class RequisicaoCompraResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete'];
    }

    protected static ?string $model = RequisicaoCompra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static UnitEnum|string|null $navigationGroup = 'Suprimentos';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'requisição de compra';

    protected static ?string $pluralModelLabel = 'Requisições de compra';

    protected static ?string $navigationLabel = 'Compras';

    protected static ?string $slug = 'suprimentos/compras';

    protected static ?string $recordTitleAttribute = 'numero';

    /**
     * @return Builder<RequisicaoCompra>
     */
    public static function getEloquentQuery(): Builder
    {
        $usuario = auth()->user();

        if (! $usuario) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return app(ComprasService::class)->requisicoesVisiveis($usuario);
    }

    public static function form(Schema $schema): Schema
    {
        return RequisicaoCompraForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RequisicaoCompraInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequisicoesCompraTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequisicoesCompra::route('/'),
            'create' => CreateRequisicaoCompra::route('/create'),
            'view' => ViewRequisicaoCompra::route('/{record}'),
            'edit' => EditRequisicaoCompra::route('/{record}/edit'),
        ];
    }
}
