<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Schemas;

use App\Models\Pessoa;
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
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('turma_id')
                                    ->label('Turma')
                                    ->relationship('turma', 'nome')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
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
