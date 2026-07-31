<?php

namespace App\Filament\Resources\Pais\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PaisForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sigla')
                    ->maxLength(3),
                Forms\Components\TextInput::make('codigo')
                    ->label('Código INEP / Educacenso')
                    ->maxLength(10),
            ]);
    }
}
