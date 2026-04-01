<?php

namespace App\Filament\Resources\Avaliacaos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;

class AvaliacaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('etapa_avaliativa_id')
                    ->relationship('etapaAvaliativa', 'nome')
                    ->label('Etapa Avaliativa')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('turma_id')
                    ->relationship('turma', 'nome')
                    ->label('Turma')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('disciplina_id')
                    ->relationship('disciplina', 'nome')
                    ->label('Disciplina')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('professor_id')
                    ->relationship('professor', 'nome')
                    ->label('Professor')
                    ->required()
                    ->searchable()
                    ->preload(),

                DatePicker::make('data_prevista')
                    ->label('Data Prevista')
                    ->required(),

                TextInput::make('nota_maxima')
                    ->label('Nota Máxima')
                    ->numeric()
                    ->default(10.00)
                    ->required(),

                TextInput::make('peso_etapa_avaliativa')
                    ->label('Peso na Etapa')
                    ->numeric()
                    ->default(1.00)
                    ->required(),
            ]);
    }
}
