<?php

namespace App\Filament\Resources\Estados;

use App\Filament\Resources\Estados\Pages\CreateEstado;
use App\Filament\Resources\Estados\Pages\EditEstado;
use App\Filament\Resources\Estados\Pages\ListEstados;
use App\Filament\Resources\Estados\Schemas\EstadoForm;
use App\Filament\Resources\Estados\Tables\EstadosTable;
use App\Models\Estado;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EstadoResource extends Resource
{
    protected static ?string $model = Estado::class;

    protected static ?string $modelLabel = 'Estado';

    protected static ?string $pluralModelLabel = 'Estados';

    protected static string|\UnitEnum|null $navigationGroup = 'Localização e Cadastros';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeEuropeAfrica;

    public static function form(Schema $schema): Schema
    {
        return EstadoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EstadosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CidadesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEstados::route('/'),
            'create' => CreateEstado::route('/create'),
            'edit' => EditEstado::route('/{record}/edit'),
        ];
    }
}
