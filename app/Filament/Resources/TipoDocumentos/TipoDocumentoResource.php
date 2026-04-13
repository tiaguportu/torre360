<?php

namespace App\Filament\Resources\TipoDocumentos;

use App\Filament\Resources\TipoDocumentos\Pages\CreateTipoDocumento;
use App\Filament\Resources\TipoDocumentos\Pages\EditTipoDocumento;
use App\Filament\Resources\TipoDocumentos\Pages\ListTipoDocumentos;
use App\Filament\Resources\TipoDocumentos\Schemas\TipoDocumentoForm;
use App\Filament\Resources\TipoDocumentos\Tables\TipoDocumentosTable;
use App\Models\TipoDocumento;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TipoDocumentoResource extends Resource
{
    protected static ?string $model = TipoDocumento::class;

    protected static ?string $modelLabel = 'Tipo de Documento';

    protected static ?string $pluralModelLabel = 'Tipos de Documentos';

    protected static string|\UnitEnum|null $navigationGroup = 'Documentos';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperClip;

    public static function form(Schema $schema): Schema
    {
        return TipoDocumentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TipoDocumentosTable::configure($table);
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
            'index' => ListTipoDocumentos::route('/'),
            'create' => CreateTipoDocumento::route('/create'),
            'edit' => EditTipoDocumento::route('/{record}/edit'),
        ];
    }
}
