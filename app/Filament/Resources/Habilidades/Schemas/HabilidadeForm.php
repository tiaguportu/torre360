<?php

namespace App\Filament\Resources\Habilidades\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HabilidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Habilidade')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('codigo')
                            ->label('Código BNCC')
                            ->maxLength(255),
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(255),
                        Select::make('tipo')
                            ->options([
                                'BNCC' => 'BNCC',
                                'Institucional' => 'Institucional',
                            ])
                            ->required()
                            ->default('BNCC'),
                        Select::make('campo_experiencia_id')
                            ->relationship('campoExperiencia', 'nome')
                            ->label('Campo de Experiência')
                            ->searchable()
                            ->preload(),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
