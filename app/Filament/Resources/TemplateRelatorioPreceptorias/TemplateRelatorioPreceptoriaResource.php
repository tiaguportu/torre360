<?php

namespace App\Filament\Resources\TemplateRelatorioPreceptorias;

use App\Filament\Resources\TemplateRelatorioPreceptorias\Pages\CreateTemplateRelatorioPreceptoria;
use App\Filament\Resources\TemplateRelatorioPreceptorias\Pages\EditTemplateRelatorioPreceptoria;
use App\Filament\Resources\TemplateRelatorioPreceptorias\Pages\ListTemplateRelatorioPreceptorias;
use App\Filament\Resources\TemplateRelatorioPreceptorias\Schemas\TemplateRelatorioPreceptoriaForm;
use App\Filament\Resources\TemplateRelatorioPreceptorias\Tables\TemplateRelatorioPreceptoriasTable;
use App\Models\TemplateRelatorioPreceptoria;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TemplateRelatorioPreceptoriaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = TemplateRelatorioPreceptoria::class;

    protected static ?string $modelLabel = 'Template de Relatório';

    protected static ?string $pluralModelLabel = 'Templates de Relatório';

    protected static string|\UnitEnum|null $navigationGroup = 'Preceptoria';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'nome';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    public static function form(Schema $schema): Schema
    {
        return TemplateRelatorioPreceptoriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemplateRelatorioPreceptoriasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplateRelatorioPreceptorias::route('/'),
            'create' => CreateTemplateRelatorioPreceptoria::route('/create'),
            'edit' => EditTemplateRelatorioPreceptoria::route('/{record}/edit'),
        ];
    }
}
