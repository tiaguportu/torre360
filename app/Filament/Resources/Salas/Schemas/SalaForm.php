<?php

namespace App\Filament\Resources\Salas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sala')
                    ->columns(2)
                    ->schema([
                        Select::make('unidade_id')
                            ->label('Unidade')
                            ->relationship('unidade', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('nome')
                            ->label('Nome / Identificação')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('capacidade')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Usado para avisar quando uma turma maior que a sala for agendada nela.'),
                        TextInput::make('tipo')
                            ->helperText('Ex.: Sala de aula, Laboratório de Informática, Quadra, Auditório.')
                            ->maxLength(255),
                        Toggle::make('ativa')
                            ->label('Ativa')
                            ->default(true)
                            ->helperText('Salas inativas não aparecem para seleção na grade horária.'),
                    ]),
            ]);
    }
}
