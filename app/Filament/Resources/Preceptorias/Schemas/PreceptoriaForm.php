<?php

namespace App\Filament\Resources\Preceptorias\Schemas;

use App\Models\Matricula;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                            ->required(),

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
                                'intervalo' => 'Intervalo de Datas (Range)',
                            ])
                            ->default('datas_especificas')
                            ->live()
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

                        DatePicker::make('data_inicio_range')
                            ->label('Data Inicial')
                            ->required(fn (Get $get) => $get('tipo_selecao_data') === 'intervalo')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->live()
                            ->visible(fn (string $operation, Get $get) => $operation === 'create' && $get('tipo_selecao_data') === 'intervalo'),

                        DatePicker::make('data_fim_range')
                            ->label('Data Final')
                            ->required(fn (Get $get) => $get('tipo_selecao_data') === 'intervalo')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(fn (Get $get) => $get('data_inicio_range'))
                            ->visible(fn (string $operation, Get $get) => $operation === 'create' && $get('tipo_selecao_data') === 'intervalo'),

                        CheckboxList::make('dias_semana_range')
                            ->label('Dias da Semana (Opcional - selecione para filtrar os dias no intervalo)')
                            ->options([
                                1 => 'Segunda-feira',
                                2 => 'Terça-feira',
                                3 => 'Quarta-feira',
                                4 => 'Quinta-feira',
                                5 => 'Sexta-feira',
                                6 => 'Sábado',
                                0 => 'Domingo',
                            ])
                            ->columns(4)
                            ->visible(fn (string $operation, Get $get) => $operation === 'create' && $get('tipo_selecao_data') === 'intervalo')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('Vínculos')
                    ->schema([
                        Select::make('professor_id')
                            ->label('Professor(a)')
                            ->relationship(
                                'professor',
                                'nome',
                                fn (Builder $query) => $query
                                    ->when(
                                        auth()->user()?->hasRole('professor') && ! auth()->user()?->hasAnyRole(['super_admin', 'admin', 'secretaria']),
                                        fn ($q) => $q->whereIn('id', auth()->user()?->pessoas->pluck('id'))
                                    )
                                    ->orderBy('nome')
                            )
                            ->default(function () {
                                $user = auth()->user();
                                if ($user?->hasRole('professor') && $user?->pessoas->count() === 1) {
                                    return $user?->pessoas->first()?->id;
                                }

                                return null;
                            })
                            ->disabled(fn () => auth()->user()?->hasRole('professor') && ! auth()->user()?->hasAnyRole(['super_admin', 'admin', 'secretaria']) && auth()->user()?->pessoas->count() === 1)
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
