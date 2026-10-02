<?php

namespace App\Filament\Resources\SubstituicaoProfessors\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubstituicaoProfessorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Substituição de Professor')
                    ->columns(2)
                    ->schema([
                        Select::make('turma_id')
                            ->label('Turma')
                            ->relationship('turma', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('disciplina_id')
                            ->label('Disciplina')
                            ->relationship('disciplina', 'nome')
                            ->searchable()
                            ->preload()
                            ->helperText('Deixe em branco para substituição do professor conselheiro da turma.'),
                        Select::make('professor_titular_id')
                            ->label('Professor Titular')
                            ->relationship('professorTitular', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('professor_substituto_id')
                            ->label('Professor Substituto')
                            ->relationship('professorSubstituto', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('data_inicio')
                            ->label('Início')
                            ->native(false)
                            ->required(),
                        DatePicker::make('data_fim')
                            ->label('Fim')
                            ->native(false)
                            ->helperText('Deixe em branco se a substituição ainda não tem data prevista para terminar.'),
                        Textarea::make('motivo')
                            ->label('Motivo')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
