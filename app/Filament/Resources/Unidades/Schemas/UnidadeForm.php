<?php

namespace App\Filament\Resources\Unidades\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UnidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('endereco_id')
                    ->relationship('endereco', 'logradouro')
                    ->searchable()
                    ->preload(),
                TextInput::make('nome')
                    ->required(),
                TextInput::make('cnpj'),
                Toggle::make('flag_ativo')
                    ->required(),
            ]);
    }
}
