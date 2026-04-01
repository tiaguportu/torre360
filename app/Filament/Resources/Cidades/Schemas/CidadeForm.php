<?php

namespace App\Filament\Resources\Cidades\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('estado_id')
                    ->relationship('estado', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nome')
                    ->required(),
                TextInput::make('codigo_ibge'),
            ]);
    }
}
