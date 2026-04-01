<?php

namespace App\Filament\Resources\Series\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SerieForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('curso_id')
                    ->relationship('curso', 'nome_interno')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nome')
                    ->required(),
                TextInput::make('sistema_avaliacao')
                    ->required(),
                TextInput::make('id_mec'),
                TextInput::make('idade_minima')
                    ->numeric(),
                Toggle::make('emite_certificado')
                    ->required(),
            ]);
    }
}
