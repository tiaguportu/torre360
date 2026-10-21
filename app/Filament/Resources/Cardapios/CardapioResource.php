<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cardapios;

use App\Filament\Resources\Cardapios\Pages\CreateCardapio;
use App\Filament\Resources\Cardapios\Pages\EditCardapio;
use App\Filament\Resources\Cardapios\Pages\ListCardapios;
use App\Filament\Resources\Cardapios\Pages\RestricoesAlimentares;
use App\Filament\Resources\Cardapios\Schemas\CardapioForm;
use App\Filament\Resources\Cardapios\Tables\CardapiosTable;
use App\Models\Cardapio;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Cardápio das refeições da escola, com alérgenos declarados, publicado no Portal da Família. O cruzamento com as
 * restrições alimentares da ficha médica (dado de saúde) exige a permissão `VerAlergicos:Cardapio`.
 */
class CardapioResource extends Resource implements HasShieldPermissions
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
            'publicar',
            'ver_alergicos',
        ];
    }

    protected static ?string $model = Cardapio::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static UnitEnum|string|null $navigationGroup = 'Operacional';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Refeição do Cardápio';

    protected static ?string $pluralModelLabel = 'Cardápio';

    protected static ?string $navigationLabel = 'Cardápio';

    protected static ?string $recordTitleAttribute = 'descricao';

    public static function form(Schema $schema): Schema
    {
        return CardapioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CardapiosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCardapios::route('/'),
            'create' => CreateCardapio::route('/create'),
            'restricoes' => RestricoesAlimentares::route('/restricoes-alimentares'),
            'edit' => EditCardapio::route('/{record}/edit'),
        ];
    }
}
