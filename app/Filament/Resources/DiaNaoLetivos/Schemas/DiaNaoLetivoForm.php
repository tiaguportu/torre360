<?php

namespace App\Filament\Resources\DiaNaoLetivos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DiaNaoLetivoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('periodo_letivo_id')
                    ->relationship('periodoLetivo', 'nome')
                    ->required()
                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\RelationManagers\RelationManager),

                DatePicker::make('data')
                    ->required(),
                TextInput::make('descricao')
                    ->required(),
                Toggle::make('flag_ativo')
                    ->required(),
            ]);
    }
}
