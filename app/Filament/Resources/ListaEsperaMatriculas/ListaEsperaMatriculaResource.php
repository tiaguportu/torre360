<?php

namespace App\Filament\Resources\ListaEsperaMatriculas;

use App\Filament\Resources\ListaEsperaMatriculas\Pages\CreateListaEsperaMatricula;
use App\Filament\Resources\ListaEsperaMatriculas\Pages\EditListaEsperaMatricula;
use App\Filament\Resources\ListaEsperaMatriculas\Pages\ListListaEsperaMatriculas;
use App\Filament\Resources\ListaEsperaMatriculas\Schemas\ListaEsperaMatriculaForm;
use App\Filament\Resources\ListaEsperaMatriculas\Tables\ListaEsperaMatriculasTable;
use App\Models\ListaEsperaMatricula;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ListaEsperaMatriculaResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    protected static ?string $model = ListaEsperaMatricula::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?string $modelLabel = 'Lista de Espera';

    protected static ?string $pluralModelLabel = 'Lista de Espera';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return ListaEsperaMatriculaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ListaEsperaMatriculasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListaEsperaMatriculas::route('/'),
            'create' => CreateListaEsperaMatricula::route('/create'),
            'edit' => EditListaEsperaMatricula::route('/{record}/edit'),
        ];
    }
}
