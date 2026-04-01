<?php

namespace App\Filament\Resources\AreaConhecimentos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AreaConhecimentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
