<?php

namespace App\Filament\Resources\CentroCustos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CentroCustoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Nome do Centro de Custo')
                    ->required()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true)
                    ->required(),
            ]);
    }
}
