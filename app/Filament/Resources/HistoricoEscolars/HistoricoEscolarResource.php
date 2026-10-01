<?php

namespace App\Filament\Resources\HistoricoEscolars;

use App\Filament\Resources\HistoricoEscolars\Pages\CreateHistoricoEscolar;
use App\Filament\Resources\HistoricoEscolars\Pages\EditHistoricoEscolar;
use App\Filament\Resources\HistoricoEscolars\Pages\ListHistoricoEscolars;
use App\Filament\Resources\HistoricoEscolars\Schemas\HistoricoEscolarForm;
use App\Filament\Resources\HistoricoEscolars\Tables\HistoricoEscolarsTable;
use App\Models\HistoricoEscolar;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HistoricoEscolarResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = HistoricoEscolar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Histórico Escolar Multi-Ano';

    protected static ?string $modelLabel = 'Histórico Escolar';

    protected static ?string $pluralModelLabel = 'Históricos Escolares Multi-Ano';

    protected static ?int $navigationSort = 17;

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
        return HistoricoEscolarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HistoricoEscolarsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistoricoEscolars::route('/'),
            'create' => CreateHistoricoEscolar::route('/create'),
            'edit' => EditHistoricoEscolar::route('/{record}/edit'),
        ];
    }
}
