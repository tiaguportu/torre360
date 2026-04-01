<?php

namespace App\Filament\Resources\SituacaoDocumentoInseridos;

use App\Filament\Resources\SituacaoDocumentoInseridos\Pages\CreateSituacaoDocumentoInserido;
use App\Filament\Resources\SituacaoDocumentoInseridos\Pages\EditSituacaoDocumentoInserido;
use App\Filament\Resources\SituacaoDocumentoInseridos\Pages\ListSituacaoDocumentoInseridos;
use App\Filament\Resources\SituacaoDocumentoInseridos\Schemas\SituacaoDocumentoInseridoForm;
use App\Filament\Resources\SituacaoDocumentoInseridos\Tables\SituacaoDocumentoInseridosTable;
use App\Models\SituacaoDocumentoInserido;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SituacaoDocumentoInseridoResource extends Resource
{
    protected static ?string $model = SituacaoDocumentoInserido::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $recordTitleAttribute = 'nome\nnome';

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
