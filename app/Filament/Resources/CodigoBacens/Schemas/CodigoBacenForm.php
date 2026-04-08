<?php

namespace App\Filament\Resources\CodigoBacens\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CodigoBacenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo')
                    ->required(),
                TextInput::make('nome_extenso')
                    ->required(),
                TextInput::make('nome_reduzido'),
                TextInput::make('ispb'),
            ]);
    }
}
