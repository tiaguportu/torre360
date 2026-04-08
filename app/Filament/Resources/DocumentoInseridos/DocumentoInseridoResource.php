<?php

namespace App\Filament\Resources\DocumentoInseridos;

use App\Filament\Resources\DocumentoInseridos\Pages\CreateDocumentoInserido;
use App\Filament\Resources\DocumentoInseridos\Pages\EditDocumentoInserido;
use App\Filament\Resources\DocumentoInseridos\Pages\ListDocumentoInseridos;
use App\Filament\Resources\DocumentoInseridos\Pages\ViewDocumentoInserido;
use App\Filament\Resources\DocumentoInseridos\Schemas\DocumentoInseridoForm;
use App\Filament\Resources\DocumentoInseridos\Schemas\DocumentoInseridoInfolist;
use App\Filament\Resources\DocumentoInseridos\Tables\DocumentoInseridosTable;
use App\Models\DocumentoInserido;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DocumentoInseridoResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = DocumentoInserido::class;

    protected static ?string $modelLabel = 'Documento Inserido';

    protected static ?string $pluralModelLabel = 'Documentos Inseridos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return DocumentoInseridoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentoInseridoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentoInseridosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentoInseridos::route('/'),
            'create' => CreateDocumentoInserido::route('/create'),
            'view' => ViewDocumentoInserido::route('/{record}'),
            'edit' => EditDocumentoInserido::route('/{record}/edit'),
        ];
    }
}
