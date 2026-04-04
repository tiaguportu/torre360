<?php

namespace App\Filament\Resources\TributacaoCursos;

use App\Filament\Resources\Concerns\HasNavigationBadge;
use App\Filament\Resources\TributacaoCursos\Pages\CreateTributacaoCurso;
use App\Filament\Resources\TributacaoCursos\Pages\EditTributacaoCurso;
use App\Filament\Resources\TributacaoCursos\Pages\ListTributacaoCursos;
use App\Filament\Resources\TributacaoCursos\Schemas\TributacaoCursoForm;
use App\Filament\Resources\TributacaoCursos\Tables\TributacaoCursosTable;
use App\Models\TributacaoCurso;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TributacaoCursoResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = TributacaoCurso::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    public static function form(Schema $schema): Schema
    {
        return TributacaoCursoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TributacaoCursosTable::configure($table);
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
            'index' => ListTributacaoCursos::route('/'),
            'create' => CreateTributacaoCurso::route('/create'),
            'edit' => EditTributacaoCurso::route('/{record}/edit'),
        ];
    }
}
