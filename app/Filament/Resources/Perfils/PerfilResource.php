<?php

namespace App\Filament\Resources\Perfils;

use App\Filament\Resources\Perfils\Pages\CreatePerfil;
use App\Filament\Resources\Perfils\Pages\EditPerfil;
use App\Filament\Resources\Perfils\Pages\ListPerfils;
use App\Filament\Resources\Perfils\Pages\ViewPerfil;
use App\Filament\Resources\Perfils\Schemas\PerfilForm;
use App\Filament\Resources\Perfils\Schemas\PerfilInfolist;
use App\Filament\Resources\Perfils\Tables\PerfilsTable;
use App\Models\Perfil;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PerfilResource extends Resource
{
    protected static ?string $model = Perfil::class;

    protected static ?string $modelLabel = 'Perfil';

    protected static ?string $pluralModelLabel = 'Perfis';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return PerfilForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerfilInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerfilsTable::configure($table);
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
            'index' => ListPerfils::route('/'),
            'create' => CreatePerfil::route('/create'),
            'view' => ViewPerfil::route('/{record}'),
            'edit' => EditPerfil::route('/{record}/edit'),
        ];
    }
}
