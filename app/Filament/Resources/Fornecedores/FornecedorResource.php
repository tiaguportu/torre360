<?php

namespace App\Filament\Resources\Fornecedores;

use App\Filament\Resources\Fornecedores\Pages\CreateFornecedor;
use App\Filament\Resources\Fornecedores\Pages\EditFornecedor;
use App\Filament\Resources\Fornecedores\Pages\ListFornecedores;
use App\Filament\Resources\Fornecedores\Schemas\FornecedorForm;
use App\Filament\Resources\Fornecedores\Tables\FornecedoresTable;
use App\Models\Fornecedor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FornecedorResource extends Resource
{
    protected static ?string $model = Fornecedor::class;

    protected static ?string $modelLabel = 'Fornecedor';

    protected static ?string $pluralModelLabel = 'Fornecedores';

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function form(Schema $schema): Schema
    {
        return FornecedorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FornecedoresTable::configure($table);
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
            'index' => ListFornecedores::route('/'),
            'create' => CreateFornecedor::route('/create'),
            'edit' => EditFornecedor::route('/{record}/edit'),
        ];
    }
}
