<?php

namespace App\Filament\Resources\PeriodoLetivos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PeriodoLetivoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required(),
                DatePicker::make('data_inicio')
                    ->required(),
                DatePicker::make('data_fim')
                    ->required(),
            ]);
    }
}
