<?php

namespace App\Filament\Resources\Bancos;

use App\Filament\Resources\Bancos\Pages\CreateBanco;
use App\Filament\Resources\Bancos\Pages\EditBanco;
use App\Filament\Resources\Bancos\Pages\ListBancos;
use App\Filament\Resources\Bancos\Schemas\BancoForm;
use App\Filament\Resources\Bancos\Tables\BancosTable;
use App\Models\Banco;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BancoResource extends Resource
{
    protected static ?string $model = Banco::class;

    protected static ?string $modelLabel = 'Banco';

    protected static ?string $pluralModelLabel = 'Bancos';

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 7;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    public static function form(Schema $schema): Schema
    {
        return BancoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BancosTable::configure($table);
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
            'index' => ListBancos::route('/'),
            'create' => CreateBanco::route('/create'),
            'edit' => EditBanco::route('/{record}/edit'),
        ];
    }
}
