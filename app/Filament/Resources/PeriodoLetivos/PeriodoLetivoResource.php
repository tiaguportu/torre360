<?php

namespace App\Filament\Resources\PeriodoLetivos;

use App\Filament\Resources\PeriodoLetivos\Pages\CreatePeriodoLetivo;
use App\Filament\Resources\PeriodoLetivos\Pages\EditPeriodoLetivo;
use App\Filament\Resources\PeriodoLetivos\Pages\ListPeriodoLetivos;
use App\Filament\Resources\PeriodoLetivos\Pages\ViewPeriodoLetivo;
use App\Filament\Resources\PeriodoLetivos\RelationManagers\DiaNaoLetivosRelationManager;
use App\Filament\Resources\PeriodoLetivos\Schemas\PeriodoLetivoForm;
use App\Filament\Resources\PeriodoLetivos\Schemas\PeriodoLetivoInfolist;
use App\Filament\Resources\PeriodoLetivos\Tables\PeriodoLetivosTable;
use App\Models\PeriodoLetivo;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PeriodoLetivoResource extends Resource
{
    protected static ?string $model = PeriodoLetivo::class;

    protected static ?string $modelLabel = 'Período Letivo';

    protected static ?string $pluralModelLabel = 'Períodos Letivos';

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return PeriodoLetivoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PeriodoLetivoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PeriodoLetivosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DiaNaoLetivosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeriodoLetivos::route('/'),
            'create' => CreatePeriodoLetivo::route('/create'),
            'view' => ViewPeriodoLetivo::route('/{record}'),
            'edit' => EditPeriodoLetivo::route('/{record}/edit'),
        ];
    }
}
