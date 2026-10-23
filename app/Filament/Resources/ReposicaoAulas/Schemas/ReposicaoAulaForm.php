<?php

namespace App\Filament\Resources\ReposicaoAulas\Schemas;

use App\Models\Disciplina;
use App\Models\Pessoa;
use App\Models\Turma;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReposicaoAulaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Reposição')
                    ->description('Defina a turma, disciplina, professor e horários para reposição de carga letiva.')
                    ->components([
                        Grid::make(3)
                            ->components([
                                Select::make('turma_id')
                                    ->label('Turma')
                                    ->options(Turma::pluck('nome', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                Select::make('disciplina_id')
                                    ->label('Disciplina')
                                    ->options(Disciplina::pluck('nome', 'id'))
                                    ->searchable()
                                    ->required(),

                                Select::make('pessoa_id')
                                    ->label('Professor Responsável')
                                    ->options(Pessoa::pluck('nome', 'id'))
                                    ->searchable()
                                    ->nullable(),
                            ]),

                        Grid::make(3)
                            ->components([
                                DatePicker::make('data_reposicao')
                                    ->label('Data da Reposição')
                                    ->required()
                                    ->default(now()),

                                TimePicker::make('hora_inicio')
                                    ->label('Horário Início')
                                    ->seconds(false)
                                    ->required()
                                    ->default('08:00'),

                                TimePicker::make('hora_fim')
                                    ->label('Horário Término')
                                    ->seconds(false)
                                    ->required()
                                    ->default('08:50'),
                            ]),

                        Grid::make(2)
                            ->components([
                                TextInput::make('motivo')
                                    ->label('Motivo da Reposição')
                                    ->placeholder('Ex: Compensação de feriado, ausência justificada do docente...')
                                    ->required()
                                    ->columnSpan(1),

                                Select::make('status')
                                    ->label('Situação')
                                    ->options([
                                        'agendada' => 'Agendada',
                                        'realizada' => 'Realizada',
                                        'cancelada' => 'Cancelada',
                                    ])
                                    ->default('agendada')
                                    ->required(),
                            ]),

                        Textarea::make('observacoes')
                            ->label('Observações e Conteúdo Programático')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
