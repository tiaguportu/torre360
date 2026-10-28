<?php

namespace App\Filament\Resources\Preceptorias\Schemas;

use App\Models\Matricula;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PreceptoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da Preceptoria')
                    ->schema([
                        Select::make('ciclo_preceptoria_id')
                            ->relationship('cicloPreceptoria', 'nome')
                            ->label('Ciclo de Preceptoria')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                if ($get('tipo_selecao_data') === 'intervalo') {
                                    PreceptoriaRecorrenciaForm::sugerirJanelaDoCiclo($get, $set);
                                }
                            }),

                        TimePicker::make('hora_inicio')
                            ->label('Hora Início')
                            ->required()
                            ->seconds(false),

                        TimePicker::make('hora_fim')
                            ->label('Hora Fim')
                            ->seconds(false)
                            ->nullable(),

                        DatePicker::make('data')
                            ->label('Data')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->visible(fn (string $operation) => $operation !== 'create'),

                        Select::make('tipo_selecao_data')
                            ->label('Modo de Seleção de Datas')
                            ->options([
                                'datas_especificas' => 'Datas Específicas (Avulsas)',
                                'intervalo' => 'Recorrente (janela de datas)',
                            ])
                            ->default('datas_especificas')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if ($state === 'intervalo') {
                                    PreceptoriaRecorrenciaForm::sugerirJanelaDoCiclo($get, $set);
                                }
                            })
                            ->required()
                            ->visible(fn (string $operation) => $operation === 'create')
                            ->columnSpanFull(),

                        Repeater::make('datas')
                            ->label('Datas das Preceptorias')
                            ->simple(
                                DatePicker::make('data')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                            )
                            ->addActionLabel('Adicionar outra data')
                            ->default([now()->format('Y-m-d')])
                            ->minItems(1)
                            ->required(fn (Get $get) => $get('tipo_selecao_data') === 'datas_especificas')
                            ->visible(fn (string $operation, Get $get) => $operation === 'create' && $get('tipo_selecao_data') === 'datas_especificas')
                            ->columnSpanFull(),

                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Recorrência')
                    ->description('A mesma preceptoria repetida na janela de datas, pulando o que for desconsiderado.')
                    ->schema(PreceptoriaRecorrenciaForm::componentes())
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (string $operation, Get $get) => $operation === 'create' && $get('tipo_selecao_data') === 'intervalo'),

                Section::make('Vínculos')
                    ->schema([
                        Select::make('professor_id')
                            ->label('Professor(a)')
                            ->relationship(
                                'professor',
                                'nome',
                                fn (Builder $query) => $query
                                    ->when(
                                        auth()->user()?->atuaComoProfessor(),
                                        fn ($q) => $q->whereIn('id', auth()->user()?->pessoas->pluck('id'))
                                    )
                                    ->orderBy('nome')
                            )
                            ->default(function () {
                                $user = auth()->user();
                                if ($user?->atuaComoProfessor() && $user?->pessoas->count() === 1) {
                                    return $user?->pessoas->first()?->id;
                                }

                                return null;
                            })
                            ->disabled(fn () => auth()->user()?->atuaComoProfessor() && auth()->user()?->pessoas->count() === 1)
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),

                        Select::make('matricula_id')
                            ->label('Matrícula (Aluno)')
                            ->relationship(
                                'matricula',
                                'id',
                                function (Builder $query, Get $get) {
                                    $query->with(['pessoa', 'turma', 'periodoLetivo']);

                                    $professorId = $get('professor_id');

                                    if ($professorId) {
                                        $query->where(function (Builder $q) use ($professorId) {
                                            // 1. Turmas onde o professor selecionado é conselheiro
                                            $q->whereHas('turma', function (Builder $tq) use ($professorId) {
                                                $tq->where('professor_conselheiro_id', $professorId);
                                            });

                                            // 2. Turmas onde o professor selecionado tem cronograma aula
                                            $q->orWhereHas('turma.cronogramasAula', function (Builder $caq) use ($professorId) {
                                                $caq->where('pessoa_id', $professorId);
                                            });
                                        });
                                    }

                                    return $query;
                                }
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Matricula $record) => $record->label_exibicao
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
