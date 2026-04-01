<?php

namespace App\Filament\Resources\Avaliacaos;

use App\Filament\Resources\Avaliacaos\Pages\CreateAvaliacao;
use App\Filament\Resources\Avaliacaos\Pages\EditAvaliacao;
use App\Filament\Resources\Avaliacaos\Pages\ListAvaliacaos;
use App\Filament\Resources\Avaliacaos\Schemas\AvaliacaoForm;
use App\Filament\Resources\Avaliacaos\Tables\AvaliacaosTable;
use App\Models\Avaliacao;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AvaliacaoResource extends Resource
{
    protected static ?string $model = Avaliacao::class;
    
    protected static ?string $modelLabel = 'Avaliação';
    protected static ?string $pluralModelLabel = 'Avaliações';

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico'; // Academic choice for exams

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    public static function form(Schema $schema): Schema
    {
        return AvaliacaoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AvaliacaosTable::configure($table);
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
            'index' => ListAvaliacaos::route('/'),
            'create' => CreateAvaliacao::route('/create'),
            'edit' => EditAvaliacao::route('/{record}/edit'),
        ];
    }
}
