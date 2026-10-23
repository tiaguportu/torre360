<?php

namespace App\Filament\Resources\Avaliacaos\Schemas;

use App\Models\EtapaAvaliativa;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AvaliacaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuração da Avaliação')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Select::make('periodo_letivo_id')
                                    ->label('Período Letivo')
                                    ->options(fn () => PeriodoLetivo::query()->orderByDesc('data_inicio')->pluck('nome', 'id'))
                                    ->default(function () {
                                        return PeriodoLetivo::query()
                                            ->whereDate('data_inicio', '<=', now())
                                            ->whereDate('data_fim', '>=', now())
                                            ->value('id')
                                            ?? PeriodoLetivo::query()->orderByDesc('data_inicio')->value('id');
                                    })
                                    ->afterStateHydrated(function (Select $component, $record) {
                                        if ($record?->turma?->periodo_letivo_id) {
                                            $component->state($record->turma->periodo_letivo_id);
                                        }
                                    })
                                    ->dehydrated(false)
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('turma_id', null);
                                        $set('disciplina_id', null);
                                        $set('etapa_avaliativa_id', null);
                                    })
                                    ->helperText('Filtra as turmas e etapas avaliativas deste período.'),
                                Select::make('turma_id')
                                    ->label('Turma')
                                    ->relationship('turma', 'nome', modifyQueryUsing: fn (Builder $query, callable $get) => $query
                                        ->vigentes()
                                        ->when($get('periodo_letivo_id'), fn ($q, $pId) => $q->where('periodo_letivo_id', $pId))
                                        ->with(['periodoLetivo', 'turno'])
                                        ->orderBy('nome')
                                    )
                                    ->getOptionLabelFromRecordUsing(function (Turma $record): string {
                                        $periodo = $record->periodoLetivo?->nome;
                                        $turno = $record->turno?->nome;

                                        $rotulo = $record->nome ?? "Turma #{$record->id}";
                                        if ($turno) {
                                            $rotulo .= " - {$turno}";
                                        }

                                        return $periodo ? "{$rotulo} ({$periodo})" : $rotulo;
                                    })
                                    ->required()
                                    ->live()
                                    ->searchable()
                                    ->preload()
                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                        $set('disciplina_id', null);
                                        if ($state && ! $get('periodo_letivo_id')) {
                                            $periodoId = Turma::where('id', $state)->value('periodo_letivo_id');
                                            if ($periodoId) {
                                                $set('periodo_letivo_id', $periodoId);
                                            }
                                        }
                                    }),
                                Select::make('disciplina_id')
                                    ->label('Disciplina')
                                    ->required()
                                    ->live()
                                    ->options(function (callable $get) {
                                        $turmaId = $get('turma_id');
                                        if (! $turmaId) {
                                            return [];
                                        }

                                        $turma = Turma::with('disciplinas')->find($turmaId);

                                        return $turma?->disciplinas->pluck('nome', 'id')->toArray() ?? [];
                                    })
                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                        $turmaId = $get('turma_id');
                                        if ($turmaId && $state) {
                                            $user = auth()->user();
                                            if (! ($user && $user->atuaComoProfessor() && $user->pessoas->count() === 1)) {
                                                $professorId = DB::table('turma_disciplina')
                                                    ->where('turma_id', $turmaId)
                                                    ->where('disciplina_id', $state)
                                                    ->value('professor_id');

                                                if ($professorId) {
                                                    $set('professor_id', $professorId);
                                                }
                                            }
                                        }
                                    }),
                                Select::make('etapa_avaliativa_id')
                                    ->label('Etapa Avaliativa')
                                    ->relationship('etapaAvaliativa', 'nome', modifyQueryUsing: function (Builder $query, callable $get) {
                                        $periodoId = $get('periodo_letivo_id');
                                        if (! $periodoId && $get('turma_id')) {
                                            $periodoId = Turma::where('id', $get('turma_id'))->value('periodo_letivo_id');
                                        }

                                        if ($periodoId) {
                                            $query->where('periodo_letivo_id', $periodoId);
                                        }

                                        return $query->with('periodoLetivo')->orderBy('data_inicio');
                                    })
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->periodoLetivo ? "{$record->nome} ({$record->periodoLetivo->nome})" : $record->nome)
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function (callable $set, $state) {
                                        if ($state) {
                                            $etapa = EtapaAvaliativa::find($state);
                                            if ($etapa?->data_fim) {
                                                $dataFim = Carbon::parse($etapa->data_fim);
                                                $set('data_limite_lancamento', $dataFim->copy()->addDays(7)->format('Y-m-d'));
                                            }
                                        }
                                    }),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Select::make('categoria_avaliacao_id')
                                    ->relationship('categoria', 'nome')
                                    ->required(),
                                DatePicker::make('data_prevista')
                                    ->default(now())
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                                        if ($state && ! $get('data_limite_lancamento')) {
                                            $set('data_limite_lancamento', Carbon::parse($state)->addDays(15)->format('Y-m-d'));
                                        }
                                    }),
                                DatePicker::make('data_limite_lancamento')
                                    ->default(fn (callable $get) => $get('data_prevista') ? Carbon::parse($get('data_prevista'))->addDays(15) : now()->addDays(15))
                                    ->required(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('nota_maxima')
                                    ->numeric()
                                    ->default(10.00)
                                    ->required(),
                                TextInput::make('peso_etapa_avaliativa')
                                    ->numeric()
                                    ->default(1.00)
                                    ->required(),
                                Select::make('professor_id')
                                    ->relationship('professor', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->default(function () {
                                        $user = auth()->user();
                                        if ($user && $user->atuaComoProfessor()) {
                                            $pessoas = $user->pessoas;
                                            if ($pessoas->count() === 1) {
                                                return $pessoas->first()->id;
                                            }
                                        }

                                        return null;
                                    })
                                    ->disabled(function () {
                                        $user = auth()->user();
                                        if ($user && $user->atuaComoProfessor()) {
                                            return $user->pessoas->count() === 1;
                                        }

                                        return false;
                                    })
                                    ->options(function () {
                                        $user = auth()->user();
                                        if ($user && $user->atuaComoProfessor()) {
                                            return $user->pessoas->pluck('nome', 'id');
                                        }

                                        return Pessoa::all()->pluck('nome', 'id');
                                    }),
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
