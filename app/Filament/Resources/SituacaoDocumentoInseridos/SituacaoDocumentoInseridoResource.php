<?php

namespace App\Filament\Resources\SituacaoDocumentoInseridos;

use App\Filament\Resources\SituacaoDocumentoInseridos\Pages\CreateSituacaoDocumentoInserido;
use App\Filament\Resources\SituacaoDocumentoInseridos\Pages\EditSituacaoDocumentoInserido;
use App\Filament\Resources\SituacaoDocumentoInseridos\Pages\ListSituacaoDocumentoInseridos;
use App\Filament\Resources\SituacaoDocumentoInseridos\Schemas\SituacaoDocumentoInseridoForm;
use App\Filament\Resources\SituacaoDocumentoInseridos\Tables\SituacaoDocumentoInseridosTable;
use App\Models\SituacaoDocumentoInserido;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SituacaoDocumentoInseridoResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = SituacaoDocumentoInserido::class;

    protected static ?string $modelLabel = 'Situação do Documento Inserido';

    protected static ?string $pluralModelLabel = 'Situações dos Documentos Inseridos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Documentos';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return SituacaoDocumentoInseridoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SituacaoDocumentoInseridosTable::configure($table);
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
            'index' => ListSituacaoDocumentoInseridos::route('/'),
            'create' => CreateSituacaoDocumentoInserido::route('/create'),
            'edit' => EditSituacaoDocumentoInserido::route('/{record}/edit'),
        ];
    }
}
