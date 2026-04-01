<?php

namespace App\Filament\Resources\Pessoas;

use App\Filament\Resources\Pessoas\Pages\CreatePessoa;
use App\Filament\Resources\Pessoas\Pages\EditPessoa;
use App\Filament\Resources\Pessoas\Pages\ListPessoas;
use App\Filament\Resources\Pessoas\Schemas\PessoaForm;
use App\Filament\Resources\Pessoas\Tables\PessoasTable;
use App\Models\Pessoa;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

use App\Filament\Resources\Pessoas\RelationManagers\EnderecoRelationManager;
use App\Filament\Resources\Pessoas\RelationManagers\MatriculasRelationManager;

class PessoaResource extends Resource
{
    protected static ?string $model = Pessoa::class;
    protected static string|\UnitEnum|null $navigationGroup = 'Cadastros';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return PessoaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PessoasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EnderecoRelationManager::class,
            MatriculasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPessoas::route('/'),
            'create' => CreatePessoa::route('/create'),
            'edit' => EditPessoa::route('/{record}/edit'),
        ];
    }
}
