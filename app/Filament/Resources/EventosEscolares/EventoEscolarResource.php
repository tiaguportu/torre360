<?php

namespace App\Filament\Resources\EventosEscolares;

use App\Filament\Resources\EventosEscolares\Pages\CreateEventoEscolar;
use App\Filament\Resources\EventosEscolares\Pages\EditEventoEscolar;
use App\Filament\Resources\EventosEscolares\Pages\ListEventosEscolares;
use App\Filament\Resources\EventosEscolares\Schemas\EventoEscolarForm;
use App\Filament\Resources\EventosEscolares\Tables\EventosEscolaresTable;
use App\Models\EventoEscolar;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class EventoEscolarResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = EventoEscolar::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static \UnitEnum|string|null $navigationGroup = 'Comunicação Escolar';

    protected static ?string $modelLabel = 'Evento Escolar';

    protected static ?string $pluralModelLabel = 'Eventos Escolares e RSVP';

    protected static ?int $navigationSort = 1;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return EventoEscolarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventosEscolaresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventosEscolares::route('/'),
            'create' => CreateEventoEscolar::route('/create'),
            'edit' => EditEventoEscolar::route('/{record}/edit'),
        ];
    }
}
