<?php

namespace App\Filament\Resources\CicloPreceptorias\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CicloPreceptoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Ciclo')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do Ciclo')
                            ->required()
                            ->maxLength(255),

                        Select::make('periodo_letivo_id')
                            ->relationship('periodoLetivo', 'nome')
                            ->label('Período Letivo')
                            ->searchable()
                            ->preload(),

                        DatePicker::make('data_inicio')
                            ->label('Data de Início'),

                        DatePicker::make('data_fim')
                            ->label('Data de Fim'),
                    ])->columns(2),
            ]);
    }
}
