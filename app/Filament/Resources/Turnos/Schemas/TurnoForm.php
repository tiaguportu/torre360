<?php

namespace App\Filament\Resources\Turnos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class TurnoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required(),
                TimePicker::make('hora_inicio')
                    ->required(),
                TimePicker::make('hora_fim')
                    ->required(),
            ]);
    }
}
