<?php

namespace App\Filament\Resources\Habilidades;

use App\Filament\Resources\Habilidades\Pages\CreateHabilidade;
use App\Filament\Resources\Habilidades\Pages\EditHabilidade;
use App\Filament\Resources\Habilidades\Pages\ListHabilidades;
use App\Filament\Resources\Habilidades\Schemas\HabilidadeForm;
use App\Filament\Resources\Habilidades\Tables\HabilidadesTable;
use App\Models\Habilidade;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HabilidadeResource extends Resource
{
    protected static ?string $model = Habilidade::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return HabilidadeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HabilidadesTable::configure($table);
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
            'index' => ListHabilidades::route('/'),
            'create' => CreateHabilidade::route('/create'),
            'edit' => EditHabilidade::route('/{record}/edit'),
        ];
    }
}
