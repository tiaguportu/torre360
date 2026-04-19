<?php

namespace App\Filament\Resources\Avaliacaos\Schemas;

use App\Models\Turma;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AvaliacaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuração da Avaliação')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('turma_id')
                                    ->relationship('turma', 'nome')
                                    ->required()
                                    ->live()
                                    ->searchable()
                                    ->preload(),
                                Select::make('disciplina_id')
                                    ->label('Disciplina')
                                    ->required()
                                    ->options(function (callable $get) {
                                        $turmaId = $get('turma_id');
                                        if (! $turmaId) {
                                            return [];
                                        }

                                        return Turma::find($turmaId)?->disciplinas?->pluck('nome', 'id') ?? [];
                                    })
                                    ->searchable()
                                    ->preload(),
                                Select::make('etapa_avaliativa_id')
                                    ->relationship('etapaAvaliativa', 'nome')
                                    ->required(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Select::make('categoria_avaliacao_id')
                                    ->relationship('categoria', 'nome')
                                    ->required(),
                                DatePicker::make('data_prevista')
                                    ->default(now()),
                                TextInput::make('nota_maxima')
                                    ->numeric()
                                    ->default(10.00),
                            ]),
                    ]),
                Section::make('Lançamento de Notas')
                    ->description('As notas serão vinculadas aos alunos matriculados na turma.')
                    ->schema([
                        Repeater::make('notas')
                            ->relationship('notas')
                            ->schema([
                                Select::make('matricula_id')
                                    ->label('Aluno')
                                    ->required()
                                    ->options(function (callable $get) {
                                        $parent = $get('../../turma_id');
                                        if (! $parent) {
                                            return [];
                                        }
                                        $turma = Turma::with('matriculas.pessoa')->find($parent);

                                        return $turma?->matriculas->mapWithKeys(function ($m) {
                                            return [$m->id => $m->pessoa->nome];
                                        }) ?? [];
                                    })
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('valor')
                                    ->label('Nota')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(fn (callable $get) => $get('../../nota_maxima') ?? 10),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => ($state['matricula_id'] ?? null) ? "Aluno ID: {$state['matricula_id']}" : null)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('Adicionar Nota do Aluno'),
                    ]),
            ]);
    }
}
