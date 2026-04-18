<?php

namespace App\Filament\Resources\Habilidades\Schemas;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HabilidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Habilidade')
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
                        Select::make('disciplina_id')
                            ->relationship('disciplina', 'nome')
                            ->label('Disciplina Relacionada')
                            ->searchable()
                            ->preload(),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
