<?php

namespace App\Filament\Resources\NotificacoesConselhoTutelar\Schemas;

use App\Models\Matricula;
use App\Models\Turma;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotificacaoConselhoTutelarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação da Notificação (FICAI / LDB)')
                    ->description('Registro de infrequência excessiva e comunicação ao Conselho Tutelar.')
                    ->components([
                        Grid::make(3)
                            ->components([
                                Select::make('turma_id')
                                    ->label('Turma')
                                    ->options(Turma::pluck('nome', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                Select::make('matricula_id')
                                    ->label('Estudante (Matrícula)')
                                    ->options(function ($get) {
                                        $turmaId = $get('turma_id');
                                        if (! $turmaId) {
                                            return Matricula::with('pessoa')->get()->pluck('pessoa.nome', 'id');
                                        }

                                        return Matricula::where('turma_id', $turmaId)
                                            ->with('pessoa')
                                            ->get()
                                            ->pluck('pessoa.nome', 'id');
                                    })
                                    ->searchable()
                                    ->required(),

                                TextInput::make('ano_letivo')
                                    ->label('Ano Letivo')
                                    ->numeric()
                                    ->default(now()->year)
                                    ->required(),
                            ]),

                        Grid::make(4)
                            ->components([
                                TextInput::make('total_aulas_previstas')
                                    ->label('Aulas Previstas no Ano')
                                    ->numeric()
                                    ->default(800)
                                    ->required(),

                                TextInput::make('total_presencas')
                                    ->label('Presenças')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),

                                TextInput::make('total_faltas')
                                    ->label('Total de Faltas')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),

                                TextInput::make('percentual_faltas')
                                    ->label('% de Faltas no Ano')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0.0)
                                    ->required(),
                            ]),

                        Grid::make(3)
                            ->components([
                                Select::make('status')
                                    ->label('Situação do Processo')
                                    ->options([
                                        'em_analise' => 'Em Análise / Mediação Escolar',
                                        'notificado' => 'Ofício Encaminhado ao Conselho',
                                        'resolvido' => 'Resolvido / Retorno às Aulas',
                                        'arquivado' => 'Arquivado',
                                    ])
                                    ->default('em_analise')
                                    ->required(),

                                TextInput::make('nome_conselho_tutelar')
                                    ->label('Órgão de Destino')
                                    ->placeholder('Ex: Conselho Tutelar - Região Sul')
                                    ->default('Conselho Tutelar Municipal'),

                                TextInput::make('municipio_conselho')
                                    ->label('Município')
                                    ->placeholder('Ex: São Paulo / SP'),
                            ]),

                        Grid::make(2)
                            ->components([
                                DatePicker::make('data_notificacao')
                                    ->label('Data de Encaminhamento do Ofício'),

                                TextInput::make('protocolo_conselho')
                                    ->label('Nº de Protocolo no Conselho Tutelar')
                                    ->placeholder('Ex: CT-2026/04912'),
                            ]),

                        Textarea::make('observacoes')
                            ->label('Parecer Pedagógico e Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
