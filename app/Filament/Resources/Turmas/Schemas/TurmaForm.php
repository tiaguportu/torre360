<?php

namespace App\Filament\Resources\Turmas\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TurmaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Select::make('serie_id')
                    ->relationship('serie', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('turno_id')
                    ->relationship('turno', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('professor_conselheiro_id')
                    ->relationship('professorConselheiro', 'nome')
                    ->searchable(['nome', 'cpf'])
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome.($record->cpf ? " - {$record->cpf}" : ''))
                    ->preload(),
                TextInput::make('vagas_maximas')
                    ->numeric()
                    ->default(30),
                ColorPicker::make('cor')
                    ->label('Cor da Turma')
                    ->default(fn () => '#'.str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT)),
                Select::make('tipo_avaliacao')
                    ->options([
                        'notas' => 'Notas',
                        'habilidades' => 'Habilidades',
                        'hibrido' => 'Híbrido',
                    ])
                    ->required()
                    ->default('notas'),
            ]);
    }
}
