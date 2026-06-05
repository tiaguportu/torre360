<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Schemas;

use App\Models\Pessoa;
use App\Models\Turma;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AvaliacaoHabilidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação da Avaliação de Habilidade')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('turma_id')
                                    ->label('Turma')
                                    ->relationship('turma', 'nome')
                                    ->required()
                                    ->live()
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
                                Select::make('etapa_avaliativa_id')
                                    ->relationship('etapaAvaliativa', 'nome')
                                    ->label('Etapa Avaliativa')
                                    ->required(),
                                Select::make('professor_id')
                                    ->label('Professor')
                                    ->relationship('professor', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->default(function () {
                                        $user = auth()->user();
                                        if ($user && $user->hasRole('professor')) {
                                            $pessoas = $user->pessoas;
                                            if ($pessoas->count() === 1) {
                                                return $pessoas->first()->id;
                                            }
                                        }

                                        return null;
                                    })
                                    ->disabled(function () {
                                        $user = auth()->user();
                                        if ($user && $user->hasRole('professor')) {
                                            return $user->pessoas->count() === 1;
                                        }

                                        return false;
                                    })
                                    ->options(function () {
                                        $user = auth()->user();
                                        if ($user && $user->hasRole('professor')) {
                                            return $user->pessoas->pluck('nome', 'id');
                                        }

                                        return Pessoa::all()->pluck('nome', 'id');
                                    }),
                            ]),
                    ]),
            ]);
    }
}
