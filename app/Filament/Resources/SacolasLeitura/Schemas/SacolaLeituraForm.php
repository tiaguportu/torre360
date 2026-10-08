<?php

namespace App\Filament\Resources\SacolasLeitura\Schemas;

use App\Enums\StatusSacolaLeitura;
use App\Models\Pessoa;
use App\Models\Turma;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SacolaLeituraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação da Sacola de Leitura')
                    ->columns(2)
                    ->schema([
                        Select::make('turma_id')
                            ->label('Turma Destino')
                            ->options(fn () => Turma::query()->orderBy('nome')->pluck('nome', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) {
                                    return;
                                }

                                $turma = Turma::with('professorConselheiro')->find($state);
                                if (! $turma) {
                                    return;
                                }

                                if (blank($get('titulo'))) {
                                    $mes = now()->translatedFormat('F');
                                    $set('titulo', "Sacola Literária - {$turma->nome} ({$mes})");
                                }

                                if ($turma->professor_conselheiro_id && blank($get('responsavel_id'))) {
                                    $set('responsavel_id', $turma->professor_conselheiro_id);
                                }
                            }),

                        Select::make('responsavel_id')
                            ->label('Professor(a) ou Responsável pela Retirada')
                            ->options(fn () => Pessoa::query()->orderBy('nome')->pluck('nome', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('titulo')
                            ->label('Título / Descrição da Sacola')
                            ->placeholder('Ex: Sacola Literária - 1º Ano A (Outubro)')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        DatePicker::make('data_retirada')
                            ->label('Data de Retirada')
                            ->native(false)
                            ->default(now())
                            ->required(),

                        DatePicker::make('data_prevista_devolucao')
                            ->label('Devolução Prevista')
                            ->native(false)
                            ->default(now()->addDays(30))
                            ->required(),

                        Select::make('status')
                            ->label('Situação')
                            ->options(StatusSacolaLeitura::class)
                            ->default(StatusSacolaLeitura::EmCirculacao)
                            ->required()
                            ->native(false),

                        Textarea::make('observacoes')
                            ->label('Observações e Instruções')
                            ->placeholder('Projetos pedagógicos vinculados, temas trabalhados ou cuidados especiais...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
