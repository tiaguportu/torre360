<?php

namespace App\Filament\Resources\CategoriaAvaliacaos;

use App\Filament\Resources\CategoriaAvaliacaos\Pages\CreateCategoriaAvaliacao;
use App\Filament\Resources\CategoriaAvaliacaos\Pages\EditCategoriaAvaliacao;
use App\Filament\Resources\CategoriaAvaliacaos\Pages\ListCategoriaAvaliacaos;
use App\Filament\Resources\CategoriaAvaliacaos\Schemas\CategoriaAvaliacaoForm;
use App\Filament\Resources\CategoriaAvaliacaos\Tables\CategoriaAvaliacaosTable;
use App\Models\CategoriaAvaliacao;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CategoriaAvaliacaoResource extends Resource implements HasShieldPermissions
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
        ];
    }

    protected static ?string $model = CategoriaAvaliacao::class;

    protected static ?string $modelLabel = 'Categoria de Avaliação';

    protected static ?string $pluralModelLabel = 'Categorias de Avaliação';

    protected static string|\UnitEnum|null $navigationGroup = 'Avaliações';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return CategoriaAvaliacaoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriaAvaliacaosTable::configure($table);
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
            'index' => ListCategoriaAvaliacaos::route('/'),
            'create' => CreateCategoriaAvaliacao::route('/create'),
            'edit' => EditCategoriaAvaliacao::route('/{record}/edit'),
        ];
    }
}
