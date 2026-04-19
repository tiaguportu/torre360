<?php

namespace App\Filament\Resources\Interessados;

use App\Filament\Resources\Interessados\Pages\CreateInteressado;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Filament\Resources\Interessados\Schemas\InteressadoForm;
use App\Filament\Resources\Interessados\Tables\InteressadosTable;
use App\Models\Interessado;
use App\Models\StatusInteressado;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Interessado';

    protected static ?string $navigationLabel = 'Interessados / Leads';

    public static function getNavigationBadge(): ?string
    {
        /** @var StatusInteressado $status */
        $status = StatusInteressado::where('nome', 'Novo')->first();

        if (! $status) {
            return null;
        }

        $count = static::getModel()::where('status_interessado_id', $status->id)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

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
