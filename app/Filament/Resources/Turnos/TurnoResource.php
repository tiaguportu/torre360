<?php

namespace App\Filament\Resources\Turnos;

use App\Filament\Resources\Turnos\Pages\CreateTurno;
use App\Filament\Resources\Turnos\Pages\EditTurno;
use App\Filament\Resources\Turnos\Pages\ListTurnos;
use App\Filament\Resources\Turnos\Schemas\TurnoForm;
use App\Filament\Resources\Turnos\Tables\TurnosTable;
use App\Models\Turno;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TurnoResource extends Resource
{
    protected static ?string $model = Turno::class;

    protected static ?string $modelLabel = 'Turno';

    protected static ?string $pluralModelLabel = 'Turnos';

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 7;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function form(Schema $schema): Schema
    {
        return TurnoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TurnosTable::configure($table);
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
            'index' => ListTurnos::route('/'),
            'create' => CreateTurno::route('/create'),
            'edit' => EditTurno::route('/{record}/edit'),
        ];
    }
}
