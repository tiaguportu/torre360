<?php

namespace App\Filament\Resources\Faturas;

use App\Filament\Resources\Faturas\Pages\CreateFatura;
use App\Filament\Resources\Faturas\Pages\EditFatura;
use App\Filament\Resources\Faturas\Pages\ListFaturas;
use App\Filament\Resources\Faturas\Schemas\FaturaForm;
use App\Filament\Resources\Faturas\Tables\FaturasTable;
use App\Models\Fatura;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FaturaResource extends Resource
{
    protected static ?string $model = Fatura::class;

    protected static ?string $modelLabel = 'Fatura';

    protected static ?string $pluralModelLabel = 'Faturas';

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    public static function form(Schema $schema): Schema
    {
        return FaturaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FaturasTable::configure($table);
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
            'index' => ListFaturas::route('/'),
            'create' => CreateFatura::route('/create'),
            'edit' => EditFatura::route('/{record}/edit'),
        ];
    }
}
