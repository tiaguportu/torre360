<?php

namespace App\Filament\Resources\Disciplinas\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DisciplinaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('area_id')
                    ->relationship('areaConhecimento', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sigla')
                    ->maxLength(10),
                Toggle::make('flag_matricula_automatica')
                    ->label('Matrícula Automática')
                    ->default(true),
                TextInput::make('carga_horaria_semanal')
                    ->label('Carga Horária Semanal')
                    ->numeric()
                    ->default(0),
                TextInput::make('ordem_boletim')
                    ->label('Ordem no Boletim')
                    ->numeric()
                    ->helperText('Define a posição sequencial desta disciplina no boletim.')
                    ->default(0)
                    ->dehydrated(true),
                ColorPicker::make('cor')
                    ->label('Cor da Disciplina')
                    ->default(fn () => '#'.str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT)),
            ]);
    }
}
