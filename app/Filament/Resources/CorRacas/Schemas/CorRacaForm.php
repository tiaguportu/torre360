<?php

namespace App\Filament\Resources\CorRacas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CorRacaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required(),
            ]);
    }
}
