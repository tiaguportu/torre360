<?php

namespace App\Filament\Resources\Perfils\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PerfilForm
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
