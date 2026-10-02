<?php

namespace App\Filament\Resources\Livros;

use App\Filament\Resources\Livros\Pages\CreateLivro;
use App\Filament\Resources\Livros\Pages\EditLivro;
use App\Filament\Resources\Livros\Pages\ListLivros;
use App\Filament\Resources\Livros\Schemas\LivroForm;
use App\Filament\Resources\Livros\Tables\LivrosTable;
use App\Models\Livro;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LivroResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Livro::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static UnitEnum|string|null $navigationGroup = 'Biblioteca';

    protected static ?string $modelLabel = 'Livro';

    protected static ?string $pluralModelLabel = 'Livros';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function form(Schema $schema): Schema
    {
        return LivroForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LivrosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLivros::route('/'),
            'create' => CreateLivro::route('/create'),
            'edit' => EditLivro::route('/{record}/edit'),
        ];
    }
}
