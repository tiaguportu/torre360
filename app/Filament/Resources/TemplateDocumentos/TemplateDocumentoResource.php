<?php

namespace App\Filament\Resources\TemplateDocumentos;

use App\Filament\Resources\TemplateDocumentos\Pages\CreateTemplateDocumento;
use App\Filament\Resources\TemplateDocumentos\Pages\EditTemplateDocumento;
use App\Filament\Resources\TemplateDocumentos\Pages\ListTemplateDocumentos;
use App\Filament\Resources\TemplateDocumentos\Schemas\TemplateDocumentoForm;
use App\Filament\Resources\TemplateDocumentos\Tables\TemplateDocumentosTable;
use App\Models\TemplateDocumento;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TemplateDocumentoResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = TemplateDocumento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Modelos de Documentos';

    protected static ?string $modelLabel = 'Modelo de Documento';

    protected static ?string $pluralModelLabel = 'Modelos de Documentos';

    protected static ?int $navigationSort = 15;

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
        return TemplateDocumentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemplateDocumentosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplateDocumentos::route('/'),
            'create' => CreateTemplateDocumento::route('/create'),
            'edit' => EditTemplateDocumento::route('/{record}/edit'),
        ];
    }
}
