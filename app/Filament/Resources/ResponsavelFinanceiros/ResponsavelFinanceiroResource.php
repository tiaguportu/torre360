<?php

namespace App\Filament\Resources\ResponsavelFinanceiros;

use App\Filament\Resources\Concerns\HasNavigationBadge;
use App\Filament\Resources\ResponsavelFinanceiros\Pages\CreateResponsavelFinanceiro;
use App\Filament\Resources\ResponsavelFinanceiros\Pages\EditResponsavelFinanceiro;
use App\Filament\Resources\ResponsavelFinanceiros\Pages\ListResponsavelFinanceiros;
use App\Filament\Resources\ResponsavelFinanceiros\Schemas\ResponsavelFinanceiroForm;
use App\Filament\Resources\ResponsavelFinanceiros\Tables\ResponsavelFinanceirosTable;
use App\Models\ResponsavelFinanceiro;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResponsavelFinanceiroResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = ResponsavelFinanceiro::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    public static function form(Schema $schema): Schema
    {
        return ResponsavelFinanceiroForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResponsavelFinanceirosTable::configure($table);
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
            'index' => ListResponsavelFinanceiros::route('/'),
            'create' => CreateResponsavelFinanceiro::route('/create'),
            'edit' => EditResponsavelFinanceiro::route('/{record}/edit'),
        ];
    }
}
