<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConselhosTutelares;

use App\Filament\Resources\ConselhosTutelares\Pages\CreateConselhoTutelar;
use App\Filament\Resources\ConselhosTutelares\Pages\EditConselhoTutelar;
use App\Filament\Resources\ConselhosTutelares\Pages\ListConselhosTutelares;
use App\Filament\Resources\ConselhosTutelares\Schemas\ConselhoTutelarForm;
use App\Filament\Resources\ConselhosTutelares\Tables\ConselhosTutelaresTable;
use App\Models\ConselhoTutelar;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Cadastro do Conselho Tutelar a que a escola comunica as faltas excessivas (Onda 57).
 */
class ConselhoTutelarResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
        ];
    }

    protected static ?string $model = ConselhoTutelar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static UnitEnum|string|null $navigationGroup = 'Configurações';

    protected static ?string $modelLabel = 'Conselho Tutelar';

    protected static ?string $pluralModelLabel = 'Conselhos Tutelares';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return ConselhoTutelarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConselhosTutelaresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConselhosTutelares::route('/'),
            'create' => CreateConselhoTutelar::route('/create'),
            'edit' => EditConselhoTutelar::route('/{record}/edit'),
        ];
    }
}
