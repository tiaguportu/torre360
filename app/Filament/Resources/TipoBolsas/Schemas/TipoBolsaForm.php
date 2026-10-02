<?php

namespace App\Filament\Resources\TipoBolsas\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TipoBolsaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tipo de Bolsa')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('percentual_maximo')
                            ->label('Percentual Máximo (%)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required(),
                        Toggle::make('exige_aprovacao')
                            ->label('Exige Aprovação')
                            ->default(true),
                        Textarea::make('criterio_renovacao')
                            ->label('Critério de Renovação')
                            ->helperText('Texto livre: ex. frequência mínima, nota mínima, renovação anual condicionada a...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
