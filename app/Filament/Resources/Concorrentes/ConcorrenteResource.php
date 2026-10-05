<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concorrentes;

use App\Filament\Resources\Concorrentes\Pages\CreateConcorrente;
use App\Filament\Resources\Concorrentes\Pages\EditConcorrente;
use App\Filament\Resources\Concorrentes\Pages\ListConcorrentes;
use App\Filament\Resources\Concorrentes\Schemas\ConcorrenteForm;
use App\Filament\Resources\Concorrentes\Tables\ConcorrentesTable;
use App\Models\Concorrente;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ConcorrenteResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Concorrente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Concorrente & Battlecard';

    protected static ?string $pluralModelLabel = 'Concorrentes & Battlecards';

    protected static ?string $navigationLabel = 'Concorrentes & Battlecards';

    protected static ?int $navigationSort = 70;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return ConcorrenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConcorrentesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConcorrentes::route('/'),
            'create' => CreateConcorrente::route('/create'),
            'edit' => EditConcorrente::route('/{record}/edit'),
        ];
    }
}
