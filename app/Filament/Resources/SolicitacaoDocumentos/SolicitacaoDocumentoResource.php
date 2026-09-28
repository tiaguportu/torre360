<?php

namespace App\Filament\Resources\SolicitacaoDocumentos;

use App\Filament\Resources\SolicitacaoDocumentos\Pages\CreateSolicitacaoDocumento;
use App\Filament\Resources\SolicitacaoDocumentos\Pages\EditSolicitacaoDocumento;
use App\Filament\Resources\SolicitacaoDocumentos\Pages\ListSolicitacaoDocumentos;
use App\Filament\Resources\SolicitacaoDocumentos\Schemas\SolicitacaoDocumentoForm;
use App\Filament\Resources\SolicitacaoDocumentos\Tables\SolicitacaoDocumentosTable;
use App\Models\SolicitacaoDocumento;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SolicitacaoDocumentoResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = SolicitacaoDocumento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Documentos Emitidos (QR)';

    protected static ?string $modelLabel = 'Documento Emitido';

    protected static ?string $pluralModelLabel = 'Documentos Emitidos e Protocolos';

    protected static ?int $navigationSort = 16;

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
        return SolicitacaoDocumentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SolicitacaoDocumentosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSolicitacaoDocumentos::route('/'),
            'create' => CreateSolicitacaoDocumento::route('/create'),
            'edit' => EditSolicitacaoDocumento::route('/{record}/edit'),
        ];
    }
}
