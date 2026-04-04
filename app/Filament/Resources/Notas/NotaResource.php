<?php

namespace App\Filament\Resources\Notas;


use App\Filament\Resources\Notas\Pages\CreateNota;
use App\Filament\Resources\Notas\Pages\EditNota;
use App\Filament\Resources\Notas\Pages\ListNotas;
use App\Filament\Resources\Notas\Pages\ViewNota;
use App\Filament\Resources\Notas\Schemas\NotaForm;
use App\Filament\Resources\Notas\Schemas\NotaInfolist;
use App\Filament\Resources\Notas\Tables\NotasTable;
use App\Models\Nota;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class NotaResource extends Resource
{

    protected static ?string $model = Nota::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $recordTitleAttribute = 'valor';

    public static function form(Schema $schema): Schema
    {
        return NotaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NotaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotasTable::configure($table);
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
            'index' => ListNotas::route('/'),
            'create' => CreateNota::route('/create'),
            'view' => ViewNota::route('/{record}'),
            'edit' => EditNota::route('/{record}/edit'),
        ];
    }
}
