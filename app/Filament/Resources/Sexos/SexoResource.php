<?php

namespace App\Filament\Resources\Sexos;

use App\Filament\Resources\Sexos\Pages\CreateSexo;
use App\Filament\Resources\Sexos\Pages\EditSexo;
use App\Filament\Resources\Sexos\Pages\ListSexos;
use App\Filament\Resources\Sexos\Pages\ViewSexo;
use App\Filament\Resources\Sexos\Schemas\SexoForm;
use App\Filament\Resources\Sexos\Schemas\SexoInfolist;
use App\Filament\Resources\Sexos\Tables\SexosTable;
use App\Models\Sexo;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SexoResource extends Resource
{
    protected static ?string $model = Sexo::class;

    protected static ?string $modelLabel = 'Sexo';

    protected static ?string $pluralModelLabel = 'Sexos';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return SexoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SexoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SexosTable::configure($table);
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
            'index' => ListSexos::route('/'),
            'create' => CreateSexo::route('/create'),
            'view' => ViewSexo::route('/{record}'),
            'edit' => EditSexo::route('/{record}/edit'),
        ];
    }
}
