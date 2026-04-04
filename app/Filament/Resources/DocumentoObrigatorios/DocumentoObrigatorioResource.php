<?php

namespace App\Filament\Resources\DocumentoObrigatorios;


use App\Filament\Resources\DocumentoObrigatorios\Pages\CreateDocumentoObrigatorio;
use App\Filament\Resources\DocumentoObrigatorios\Pages\EditDocumentoObrigatorio;
use App\Filament\Resources\DocumentoObrigatorios\Pages\ListDocumentoObrigatorios;
use App\Filament\Resources\DocumentoObrigatorios\Schemas\DocumentoObrigatorioForm;
use App\Filament\Resources\DocumentoObrigatorios\Tables\DocumentoObrigatoriosTable;
use App\Models\DocumentoObrigatorio;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DocumentoObrigatorioResource extends Resource
{

    protected static ?string $model = DocumentoObrigatorio::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperClip;

    public static function form(Schema $schema): Schema
    {
        return DocumentoObrigatorioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentoObrigatoriosTable::configure($table);
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
            'index' => ListDocumentoObrigatorios::route('/'),
            'create' => CreateDocumentoObrigatorio::route('/create'),
            'edit' => EditDocumentoObrigatorio::route('/{record}/edit'),
        ];
    }
}
