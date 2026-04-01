<?php

namespace App\Filament\Resources\Disciplinas;

use App\Filament\Resources\Disciplinas\Pages\CreateDisciplina;
use App\Filament\Resources\Disciplinas\Pages\EditDisciplina;
use App\Filament\Resources\Disciplinas\Pages\ListDisciplinas;
use App\Filament\Resources\Disciplinas\Schemas\DisciplinaForm;
use App\Filament\Resources\Disciplinas\Tables\DisciplinasTable;
use App\Models\Disciplina;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DisciplinaResource extends Resource
{
    protected static ?string $model = Disciplina::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';
    protected static ?int $navigationSort = 4;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function form(Schema $schema): Schema
    {
        return DisciplinaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DisciplinasTable::configure($table);
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
            'index' => ListDisciplinas::route('/'),
            'create' => CreateDisciplina::route('/create'),
            'edit' => EditDisciplina::route('/{record}/edit'),
        ];
    }
}
