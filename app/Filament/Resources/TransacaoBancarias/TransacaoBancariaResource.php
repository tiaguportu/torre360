<?php

namespace App\Filament\Resources\TransacaoBancarias;

use App\Filament\Resources\TransacaoBancarias\Pages\CreateTransacaoBancaria;
use App\Filament\Resources\TransacaoBancarias\Pages\EditTransacaoBancaria;
use App\Filament\Resources\TransacaoBancarias\Pages\ListTransacaoBancarias;
use App\Filament\Resources\TransacaoBancarias\Schemas\TransacaoBancariaForm;
use App\Filament\Resources\TransacaoBancarias\Tables\TransacaoBancariasTable;
use App\Models\TransacaoBancaria;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TransacaoBancariaResource extends Resource
{
    protected static ?string $model = TransacaoBancaria::class;

    protected static ?string $modelLabel = 'Transação Bancária';

    protected static ?string $pluralModelLabel = 'Transações Bancárias';

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public static function form(Schema $schema): Schema
    {
        return TransacaoBancariaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransacaoBancariasTable::configure($table);
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
            'index' => ListTransacaoBancarias::route('/'),
            'create' => CreateTransacaoBancaria::route('/create'),
            'edit' => EditTransacaoBancaria::route('/{record}/edit'),
        ];
    }
}
