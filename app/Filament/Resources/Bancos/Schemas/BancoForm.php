<?php

namespace App\Filament\Resources\Bancos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BancoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required(),
                TextInput::make('codigo_bacen'),
                TextInput::make('agencia'),
                TextInput::make('conta'),
                TextInput::make('pix_key'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
