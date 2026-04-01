<?php

namespace App\Filament\Resources\Configuracaos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ConfiguracaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('campo')
                    ->required(),
                Textarea::make('valor')
                    ->columnSpanFull(),
                TextInput::make('grupo')
                    ->required(),
                TextInput::make('ordem')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
