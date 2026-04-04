<?php

namespace App\Filament\Resources\Series;

use App\Filament\Resources\Concerns\HasNavigationBadge;
use App\Filament\Resources\Series\Pages\CreateSerie;
use App\Filament\Resources\Series\Pages\EditSerie;
use App\Filament\Resources\Series\Pages\ListSeries;
use App\Filament\Resources\Series\RelationManagers\TurmasRelationManager;
use App\Filament\Resources\Series\Schemas\SerieForm;
use App\Filament\Resources\Series\Tables\SeriesTable;
use App\Models\Serie;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SerieResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Serie::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return SerieForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TurmasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeries::route('/'),
            'create' => CreateSerie::route('/create'),
            'edit' => EditSerie::route('/{record}/edit'),
        ];
    }
}
