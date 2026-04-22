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
                                        fn ($q) => $q->where('id', auth()->user()?->pessoa?->id)
                                    )
                                    ->orderBy('nome')
                            )
                            ->default(fn () => auth()->user()?->hasRole('professor') ? auth()->user()?->pessoa?->id : null)
                            ->disabled(fn () => auth()->user()?->hasRole('professor') && ! auth()->user()?->hasAnyRole(['super_admin', 'admin', 'secretaria']))
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('matricula_id')
                            ->label('Matrícula (Aluno)')
                            ->relationship(
                                'matricula',
                                'id',
                                fn (Builder $query) => $query->with(['pessoa', 'turma', 'periodoLetivo'])
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
