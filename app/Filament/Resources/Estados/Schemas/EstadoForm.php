<?php

namespace App\Filament\Resources\Estados\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EstadoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('pais_id')
                    ->relationship('pais', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nome')
                    ->required(),
                TextInput::make('sigla')
                    ->required(),
            ]);
    }
}
