<?php

namespace App\Filament\Resources\Notas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NotaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('avaliacao_id')
                    ->relationship('avaliacao', 'id')
                    ->required(),
                Select::make('matricula_id')
                    ->relationship('matricula', 'id')
                    ->required(),
                TextInput::make('valor')
                    ->required()
                    ->numeric(),
            ]);
    }
}
