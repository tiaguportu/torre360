<?php

namespace App\Filament\Resources\ApuracaoHoraAulas\Schemas;

use App\Models\Pessoa;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApuracaoHoraAulaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Competência e Docente')
                    ->components([
                        Grid::make(3)
                            ->components([
                                Select::make('pessoa_id')
                                    ->label('Professor')
                                    ->options(Pessoa::pluck('nome', 'id'))
                                    ->searchable()
                                    ->required(),

                                TextInput::make('ano')
                                    ->label('Ano')
                                    ->numeric()
                                    ->default(now()->year)
                                    ->required(),

                                Select::make('mes')
                                    ->label('Mês')
                                    ->options([
                                        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
                                        4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
                                        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
                                        10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                                    ])
                                    ->default(now()->month)
                                    ->required(),
                            ]),

                        Grid::make(4)
                            ->components([
                                TextInput::make('total_aulas_previstas')
                                    ->label('Aulas Previstas')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('total_aulas_ministradas')
                                    ->label('Aulas Ministradas')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('total_aulas_reposicao')
                                    ->label('Reposições Realizadas')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('total_aulas_faltadas')
                                    ->label('Faltas no Mês')
                                    ->numeric()
                                    ->default(0),
                            ]),

                        Grid::make(4)
                            ->components([
                                TextInput::make('total_horas_atividade')
                                    ->label('Horas-Atividade / Reuniões')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('valor_hora_aula')
                                    ->label('Valor da Hora-Aula (R$)')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->default(45.00),

                                TextInput::make('percentual_dsr')
                                    ->label('% DSR (CLT 1/6)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(16.67),

                                TextInput::make('valor_total_bruto')
                                    ->label('Valor Bruto Calculado')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->default(0),
                            ]),

                        Select::make('status')
                            ->label('Situação do Fechamento')
                            ->options([
                                'aberto' => 'Aberto para Conferência',
                                'conferido' => 'Conferido pelo Docente',
                                'homologado' => 'Homologado / Entregue à Contabilidade',
                                'contestado' => 'Contestado pelo Docente',
                            ])
                            ->default('aberto')
                            ->required(),

                        Textarea::make('observacoes')
                            ->label('Observações e Ajustes de Convenção')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
