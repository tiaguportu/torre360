<?php

namespace App\Filament\Resources\Preceptorias\Schemas;

use App\Models\Matricula;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
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
                            ->required(),

                        Select::make('matricula_id')
                            ->label('Matrícula (Aluno)')
                            ->relationship(
                                'matricula',
                                'id',
                                function (Builder $query) {
                                    $query->with(['pessoa', 'turma', 'periodoLetivo']);

                                    $user = auth()->user();
                                    if ($user?->hasRole('professor') && ! $user?->hasAnyRole(['super_admin', 'admin', 'secretaria'])) {
                                        $pessoaIds = $user->pessoas->pluck('id')->toArray();

                                        $query->where(function (Builder $q) use ($pessoaIds) {
                                            // 1. Turmas onde é conselheiro
                                            $q->whereHas('turma', function (Builder $tq) use ($pessoaIds) {
                                                $tq->whereIn('professor_conselheiro_id', $pessoaIds);
                                            });

                                            // 2. Turmas onde tem cronograma aula
                                            $q->orWhereHas('turma.cronogramasAula', function (Builder $caq) use ($pessoaIds) {
                                                $caq->whereIn('pessoa_id', $pessoaIds);
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
