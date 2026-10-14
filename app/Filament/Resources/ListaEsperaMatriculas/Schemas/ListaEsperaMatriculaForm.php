<?php

namespace App\Filament\Resources\ListaEsperaMatriculas\Schemas;

use App\Enums\StatusListaEspera;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Turma;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ListaEsperaMatriculaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lista de Espera')
                    ->columns(2)
                    ->schema([
                        Select::make('turma_id')
                            ->label('Turma')
                            ->options(fn () => Turma::query()
                                ->get()
                                ->mapWithKeys(function (Turma $turma) {
                                    $matriculadas = $turma->matriculas()->count();
                                    $vagas = $turma->vagas_maximas;
                                    $label = $vagas ? "{$turma->nome} ({$matriculadas}/{$vagas} vagas)" : "{$turma->nome} (sem limite de vagas)";

                                    return [$turma->id => $label];
                                }))
                            ->searchable()
                            ->required()
                            ->helperText('Turmas sem "vagas_maximas" definido não ficam lotadas, então não fazem sentido na lista de espera.'),
                        Select::make('periodo_letivo_id')
                            ->label('Período Letivo')
                            ->relationship('periodoLetivo', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('pessoa_id')
                            ->label('Aluno (Pessoa)')
                            ->relationship('pessoa', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Se o pretendente ainda não tem cadastro de Pessoa, crie-o primeiro em Cadastros → Pessoas.'),
                        Select::make('interessado_id')
                            ->label('Interessado (CRM, opcional)')
                            ->options(fn () => Interessado::query()
                                ->with('pessoa')
                                ->get()
                                ->mapWithKeys(fn (Interessado $interessado) => [$interessado->id => $interessado->pessoa?->nome ?? "Interessado #{$interessado->id}"]))
                            ->searchable(),
                        Select::make('interessado_dependente_id')
                            ->label('Dependente do Interessado (opcional)')
                            ->options(fn () => InteressadoDependente::query()
                                ->pluck('nome_crianca', 'id'))
                            ->searchable(),
                        Select::make('status')
                            ->label('Status')
                            ->options(StatusListaEspera::class)
                            ->default(StatusListaEspera::Aguardando)
                            ->required(),
                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
