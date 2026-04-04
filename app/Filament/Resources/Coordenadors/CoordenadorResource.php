<?php

namespace App\Filament\Resources\Coordenadors;


use App\Filament\Resources\Coordenadors\Pages\CreateCoordenador;
use App\Filament\Resources\Coordenadors\Pages\EditCoordenador;
use App\Filament\Resources\Coordenadors\Pages\ListCoordenadors;
use App\Filament\Resources\Coordenadors\Schemas\CoordenadorForm;
use App\Filament\Resources\Coordenadors\Tables\CoordenadorsTable;
use App\Models\Coordenador;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CoordenadorResource extends Resource
{

    protected static ?string $model = Coordenador::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function form(Schema $schema): Schema
    {
        return CoordenadorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoordenadorsTable::configure($table);
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
            'index' => ListCoordenadors::route('/'),
            'create' => CreateCoordenador::route('/create'),
            'edit' => EditCoordenador::route('/{record}/edit'),
        ];
    }
}
