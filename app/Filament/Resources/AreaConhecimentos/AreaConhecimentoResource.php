<?php

namespace App\Filament\Resources\AreaConhecimentos;

use App\Filament\Resources\AreaConhecimentos\Pages\CreateAreaConhecimento;
use App\Filament\Resources\AreaConhecimentos\Pages\EditAreaConhecimento;
use App\Filament\Resources\AreaConhecimentos\Pages\ListAreaConhecimentos;
use App\Filament\Resources\AreaConhecimentos\Schemas\AreaConhecimentoForm;
use App\Filament\Resources\AreaConhecimentos\Tables\AreaConhecimentosTable;
use App\Models\AreaConhecimento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AreaConhecimentoResource extends Resource
{
    protected static ?string $model = AreaConhecimento::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';
    protected static ?int $navigationSort = 5;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedVariable;

    public static function form(Schema $schema): Schema
    {
        return AreaConhecimentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AreaConhecimentosTable::configure($table);
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
            'index' => ListAreaConhecimentos::route('/'),
            'create' => CreateAreaConhecimento::route('/create'),
            'edit' => EditAreaConhecimento::route('/{record}/edit'),
        ];
    }
}
