<?php

namespace App\Filament\Resources\Coordenadors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;

class CoordenadorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('curso_id')
                    ->relationship('curso', 'nome_interno')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('pessoa_id')
                    ->relationship('pessoa', 'nome')
                    ->searchable(['nome', 'cpf'])
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ($record->cpf ? " - {$record->cpf}" : ""))
                    ->preload()
                    ->required(),
                TextInput::make('cargo')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('data_inicio')
                    ->required(),
                Toggle::make('flag_somente_leitura')
                    ->label('Somente Leitura')
                    ->default(false),
            ]);
    }
}
