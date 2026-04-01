<?php

namespace App\Filament\Resources\TributacaoCursos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TributacaoCursoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('curso_id')
                    ->required()
                    ->numeric(),
                TextInput::make('cnae'),
                TextInput::make('iss')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('pis')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('cofins')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('item_servico'),
            ]);
    }
}
