<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstrumentoAvaliacaoDocentes;

use App\Filament\Resources\InstrumentoAvaliacaoDocentes\Pages\CreateInstrumentoAvaliacaoDocente;
use App\Filament\Resources\InstrumentoAvaliacaoDocentes\Pages\EditInstrumentoAvaliacaoDocente;
use App\Filament\Resources\InstrumentoAvaliacaoDocentes\Pages\ListInstrumentoAvaliacaoDocentes;
use App\Filament\Resources\InstrumentoAvaliacaoDocentes\Schemas\InstrumentoAvaliacaoDocenteForm;
use App\Filament\Resources\InstrumentoAvaliacaoDocentes\Tables\InstrumentoAvaliacaoDocentesTable;
use App\Models\InstrumentoAvaliacaoDocente;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Instrumentos de avaliação docente: o roteiro de critérios e a escala usados nas observações em sala.
 */
class InstrumentoAvaliacaoDocenteResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = InstrumentoAvaliacaoDocente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 31;

    protected static ?string $modelLabel = 'Instrumento de Avaliação Docente';

    protected static ?string $pluralModelLabel = 'Instrumentos de Avaliação Docente';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return InstrumentoAvaliacaoDocenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstrumentoAvaliacaoDocentesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstrumentoAvaliacaoDocentes::route('/'),
            'create' => CreateInstrumentoAvaliacaoDocente::route('/create'),
            'edit' => EditInstrumentoAvaliacaoDocente::route('/{record}/edit'),
        ];
    }
}
