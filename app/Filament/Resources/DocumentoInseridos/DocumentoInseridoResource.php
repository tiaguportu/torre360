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
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DocumentoInseridoResource extends Resource
{
    protected static ?string $model = DocumentoInserido::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?string $recordTitleAttribute = '\n';

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
