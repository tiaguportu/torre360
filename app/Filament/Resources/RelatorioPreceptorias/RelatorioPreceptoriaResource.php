<?php

namespace App\Filament\Resources\RelatorioPreceptorias;

use App\Filament\Resources\RelatorioPreceptorias\Pages\CreateRelatorioPreceptoria;
use App\Filament\Resources\RelatorioPreceptorias\Pages\EditRelatorioPreceptoria;
use App\Filament\Resources\RelatorioPreceptorias\Pages\ListRelatorioPreceptorias;
use App\Filament\Resources\RelatorioPreceptorias\Schemas\RelatorioPreceptoriaForm;
use App\Filament\Resources\RelatorioPreceptorias\Tables\RelatorioPreceptoriasTable;
use App\Models\RelatorioPreceptoria;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RelatorioPreceptoriaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = RelatorioPreceptoria::class;

    protected static ?string $modelLabel = 'Relatório de Preceptoria';

    protected static ?string $pluralModelLabel = 'Relatórios de Preceptoria';

    protected static string|\UnitEnum|null $navigationGroup = 'Preceptoria';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return RelatorioPreceptoriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RelatorioPreceptoriasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelatorioPreceptorias::route('/'),
            'create' => CreateRelatorioPreceptoria::route('/create'),
            'edit' => EditRelatorioPreceptoria::route('/{record}/edit'),
        ];
    }
}
