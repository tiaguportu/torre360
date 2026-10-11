<?php

namespace App\Filament\Resources\RetencaoTarefas\Schemas;

use App\Enums\StatusRetencaoTarefa;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RetencaoTarefaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação e Risco do Estudante')
                    ->description('Dados da matrícula, aluno e avaliação do score de evasão.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('titulo')
                                    ->label('Título da Ação')
                                    ->disabled()
                                    ->columnSpan(2),

                                TextInput::make('score_risco')
                                    ->label('Score de Risco')
                                    ->suffix('%')
                                    ->disabled(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('status')
                                    ->label('Situação do Acompanhamento')
                                    ->options(StatusRetencaoTarefa::class)
                                    ->required(),

                                Select::make('prioridade')
                                    ->label('Prioridade')
                                    ->options([
                                        'alta' => 'Alta',
                                        'critica' => 'Crítica',
                                    ])
                                    ->required(),

                                Select::make('responsavel_user_id')
                                    ->label('Responsável')
                                    ->options(fn () => User::query()->ativos()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                            ]),

                        DatePicker::make('data_limite')
                            ->label('Prazo de Resolução')
                            ->native(false),

                        Textarea::make('descricao')
                            ->label('Diagnóstico e Fatores de Risco')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Section::make('Resolução e Ações de Retenção')
                    ->description('Registro pedagógico e comercial do contato com a família e medidas tomadas.')
                    ->schema([
                        Textarea::make('observacoes_resolucao')
                            ->label('Parecer Final / Plano de Ação')
                            ->placeholder('Descreva as negociações financeiras, apoio pedagógico ou acordo estabelecido com a família...')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
