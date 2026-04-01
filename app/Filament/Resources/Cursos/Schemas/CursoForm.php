<?php

namespace App\Filament\Resources\Cursos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CursoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('unidade_id')
                    ->relationship('unidade', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nome_externo')
                    ->required(),
                TextInput::make('nome_interno')
                    ->required(),
                TextInput::make('portaria'),
                DatePicker::make('data_final'),
                TextInput::make('minutos_por_periodo')
                    ->label('Minutos por Período')
                    ->numeric()
                    ->default(50)
                    ->minValue(1)
                    ->maxValue(300)
                    ->suffix('min')
                    ->helperText('Duração padrão de cada aula/período. Usado para calcular a hora de fim automaticamente no Cronograma de Aulas.'),
                \Filament\Forms\Components\ColorPicker::make('cor')
                    ->label('Cor do Curso')
                    ->default('#3b82f6'),
            ]);
    }
}
