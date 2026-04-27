<?php

namespace App\Filament\Resources\Preceptorias\Schemas;

use App\Models\Matricula;
use Filament\Forms\Components\DatePicker;
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

                        DatePicker::make('data')
                            ->label('Data')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        TimePicker::make('hora_inicio')
                            ->label('Hora Início')
                            ->required()
                            ->seconds(false),

                        TimePicker::make('hora_fim')
                            ->label('Hora Fim')
                            ->seconds(false)
                            ->nullable(),
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
