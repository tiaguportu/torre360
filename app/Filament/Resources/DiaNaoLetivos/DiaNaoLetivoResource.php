<?php

namespace App\Filament\Resources\DiaNaoLetivos;

use App\Filament\Resources\Concerns\HasNavigationBadge;
use App\Filament\Resources\DiaNaoLetivos\Pages\CreateDiaNaoLetivo;
use App\Filament\Resources\DiaNaoLetivos\Pages\EditDiaNaoLetivo;
use App\Filament\Resources\DiaNaoLetivos\Pages\ListDiaNaoLetivos;
use App\Filament\Resources\DiaNaoLetivos\Pages\ViewDiaNaoLetivo;
use App\Filament\Resources\DiaNaoLetivos\Schemas\DiaNaoLetivoForm;
use App\Filament\Resources\DiaNaoLetivos\Schemas\DiaNaoLetivoInfolist;
use App\Filament\Resources\DiaNaoLetivos\Tables\DiaNaoLetivosTable;
use App\Models\DiaNaoLetivo;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DiaNaoLetivoResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = DiaNaoLetivo::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $recordTitleAttribute = 'descricao';

    public static function form(Schema $schema): Schema
    {
        return DiaNaoLetivoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DiaNaoLetivoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiaNaoLetivosTable::configure($table);
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
            'index' => ListDiaNaoLetivos::route('/'),
            'create' => CreateDiaNaoLetivo::route('/create'),
            'view' => ViewDiaNaoLetivo::route('/{record}'),
            'edit' => EditDiaNaoLetivo::route('/{record}/edit'),
        ];
    }
}
