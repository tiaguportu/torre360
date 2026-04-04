<?php

namespace App\Filament\Resources\SituacaoMatriculas;

use App\Filament\Resources\Concerns\HasNavigationBadge;
use App\Filament\Resources\SituacaoMatriculas\Pages\CreateSituacaoMatricula;
use App\Filament\Resources\SituacaoMatriculas\Pages\EditSituacaoMatricula;
use App\Filament\Resources\SituacaoMatriculas\Pages\ListSituacaoMatriculas;
use App\Filament\Resources\SituacaoMatriculas\Schemas\SituacaoMatriculaForm;
use App\Filament\Resources\SituacaoMatriculas\Tables\SituacaoMatriculasTable;
use App\Models\SituacaoMatricula;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SituacaoMatriculaResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = SituacaoMatricula::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    public static function form(Schema $schema): Schema
    {
        return SituacaoMatriculaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SituacaoMatriculasTable::configure($table);
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
            'index' => ListSituacaoMatriculas::route('/'),
            'create' => CreateSituacaoMatricula::route('/create'),
            'edit' => EditSituacaoMatricula::route('/{record}/edit'),
        ];
    }
}
