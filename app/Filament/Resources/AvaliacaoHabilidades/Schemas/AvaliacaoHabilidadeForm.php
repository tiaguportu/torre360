<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Schemas;

use App\Enums\ConceitoHabilidade;
use App\Models\Turma;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AvaliacaoHabilidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação da Avaliação')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('turma_id')
                                    ->label('Turma')
                                    ->options(Turma::all()->pluck('nome', 'id'))
                                    ->required()
                                    ->live()
                                    ->dehydrated(false) // Apenas para contexto de busca
                                    ->afterStateUpdated(fn ($set) => $set('habilidade_id', null))
                                    ->searchable()
                                    ->preload(),
                                Select::make('habilidade_id')
                                    ->label('Habilidade')
                                    ->required()
                                    ->options(function (callable $get) {
                                        $turmaId = $get('turma_id');
                                        if (! $turmaId) {
                                            return [];
                                        }

                                        return Turma::find($turmaId)?->habilidades?->pluck('nome', 'id') ?? [];
                                    })
                                    ->searchable()
                                    ->preload(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('matricula_id')
                                    ->label('Aluno')
                                    ->required()
                                    ->options(function (callable $get) {
                                        $turmaId = $get('turma_id');
                                        if (! $turmaId) {
                                            return [];
                                        }

                                        return Turma::with('matriculas.pessoa')->find($turmaId)
                                            ?->matriculas->mapWithKeys(fn ($m) => [$m->id => $m->pessoa->nome]) ?? [];
                                    })
                                    ->searchable(),
                                Select::make('etapa_avaliativa_id')
                                    ->relationship('etapaAvaliativa', 'nome')
                                    ->required(),
                            ]),
                    ]),
                Section::make('Resultado')
                    ->schema([
                        Select::make('conceito')
                            ->options(ConceitoHabilidade::class)
                            ->required(),
                        Textarea::make('observacao')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
