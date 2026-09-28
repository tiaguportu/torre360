<?php

namespace App\Filament\Resources\PeriodoRematriculas;

use App\Filament\Resources\PeriodoRematriculas\Pages\CreatePeriodoRematricula;
use App\Filament\Resources\PeriodoRematriculas\Pages\EditPeriodoRematricula;
use App\Filament\Resources\PeriodoRematriculas\Pages\ListPeriodoRematriculas;
use App\Filament\Resources\PeriodoRematriculas\Schemas\PeriodoRematriculaForm;
use App\Filament\Resources\PeriodoRematriculas\Tables\PeriodoRematriculasTable;
use App\Models\PeriodoRematricula;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PeriodoRematriculaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = PeriodoRematricula::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?string $navigationLabel = 'Campanhas de Rematrícula';

    protected static ?string $modelLabel = 'Campanha de Rematrícula';

    protected static ?string $pluralModelLabel = 'Campanhas de Rematrícula';

    protected static ?int $navigationSort = 3;

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

    public static function form(Schema $schema): Schema
    {
        return PeriodoRematriculaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PeriodoRematriculasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeriodoRematriculas::route('/'),
            'create' => CreatePeriodoRematricula::route('/create'),
            'edit' => EditPeriodoRematricula::route('/{record}/edit'),
        ];
    }
}
