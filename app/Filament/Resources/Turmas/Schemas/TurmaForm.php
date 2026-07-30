<?php

namespace App\Filament\Resources\Turmas\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('codigo')
                    ->label('Código')
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
                Select::make('tipo_mediacao_didatico_pedagogica')
                    ->label('Tipo de Mediação Didático-Pedagógica')
                    ->options([
                        1 => '1 - Presencial',
                        2 => '2 - Semipresencial',
                        3 => '3 - Educação a distância – EAD',
                    ])
                    ->default(1),
                Select::make('tipo_turma')
                    ->label('Tipo de Turma')
                    ->options([
                        4 => '4 - Atividade complementar',
                        5 => '5 - Atendimento educacional especializado (AEE)',
                        6 => '6 - Curricular (etapa de ensino)',
                        9 => '9 - Curricular (etapa de ensino) com Atividade Complementar',
                    ])
                    ->default(6),
                Select::make('local_funcionamento_diferenciado')
                    ->label('Local de Funcionamento Diferenciado da Turma')
                    ->options([
                        0 => '0 - A turma não está em local de funcionamento diferenciado',
                        1 => '1 - Sala anexa',
                        2 => '2 - Unidade de atendimento socioeducativo',
                        3 => '3 - Unidade prisional',
                    ])
                    ->default(0),
                Toggle::make('turma_educacao_especial')
                    ->label('Turma de Educação Especial (Classe Especial)')
                    ->default(false),
            ]);
    }
}
