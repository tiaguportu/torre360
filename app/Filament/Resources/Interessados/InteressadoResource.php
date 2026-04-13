<?php

namespace App\Filament\Resources\Interessados;

use App\Filament\Resources\Interessados\Pages\CreateInteressado;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Filament\Resources\Interessados\Schemas\InteressadoForm;
use App\Filament\Resources\Interessados\Tables\InteressadosTable;
use App\Models\Interessado;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InteressadoResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Interessado::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Interessado';

    public static function form(Schema $schema): Schema
    {
        return InteressadoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InteressadosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\HistoricosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInteressados::route('/'),
            'kanban' => Pages\KanbanInteressados::route('/kanban'),
            'create' => CreateInteressado::route('/create'),
            'edit' => EditInteressado::route('/{record}/edit'),
        ];
    }
}
