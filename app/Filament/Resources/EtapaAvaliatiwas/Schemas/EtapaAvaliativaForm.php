<?php

namespace App\Filament\Resources\EtapaAvaliatiwas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;

class EtapaAvaliativaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('data_inicio')
                    ->label('Data de Início')
                    ->required(),

                DatePicker::make('data_fim')
                    ->label('Data de Fim')
                    ->required(),

                Select::make('periodo_letivo_id')
                    ->relationship('periodoLetivo', 'nome')
                    ->label('Período Letivo')
                    ->required()
                    ->searchable()
                    ->preload(),
            ]);
    }
}
