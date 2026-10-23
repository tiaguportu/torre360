<?php

namespace App\Filament\Resources\DocumentoRegulatorios;

use App\Filament\Resources\DocumentoRegulatorios\Pages\CreateDocumentoRegulatorio;
use App\Filament\Resources\DocumentoRegulatorios\Pages\EditDocumentoRegulatorio;
use App\Filament\Resources\DocumentoRegulatorios\Pages\ListDocumentoRegulatorios;
use App\Filament\Resources\DocumentoRegulatorios\Schemas\DocumentoRegulatorioForm;
use App\Filament\Resources\DocumentoRegulatorios\Tables\DocumentoRegulatoriosTable;
use App\Models\DocumentoRegulatorio;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DocumentoRegulatorioResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = DocumentoRegulatorio::class;

    protected static ?string $modelLabel = 'Documento Regulatório';

    protected static ?string $pluralModelLabel = 'Documentos da Escola com Validade';

    protected static string|\UnitEnum|null $navigationGroup = 'Localização e Cadastros';

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    public static function form(Schema $schema): Schema
    {
        return DocumentoRegulatorioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentoRegulatoriosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentoRegulatorios::route('/'),
            'create' => CreateDocumentoRegulatorio::route('/create'),
            'edit' => EditDocumentoRegulatorio::route('/{record}/edit'),
        ];
    }
}
