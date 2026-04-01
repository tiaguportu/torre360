<?php

namespace App\Filament\Resources\Sexos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SexoForm
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
