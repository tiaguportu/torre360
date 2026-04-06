<?php

namespace App\Filament\Resources\CronogramaAulas\Schemas;

use App\Models\Turma;
use Carbon\Carbon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CronogramaAulaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('periodo_letivo_id')
                    ->label('Período Letivo')
                    ->relationship('periodoLetivo', 'nome')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                Select::make('turma_id')
                    ->relationship('turma', 'nome')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome ?? "Turma #{$record->id}")
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        self::calcularHoraFim($get, $set);
                        if ($state && ! $get('periodo_letivo_id')) {
                            $turma = Turma::select('periodo_letivo_id')->find($state);
                            if ($turma?->periodo_letivo_id) {
                                $set('periodo_letivo_id', $turma->periodo_letivo_id);
                            }
                        }
                    }),

                Select::make('disciplina_id')
                    ->relationship('disciplina', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('pessoa_id')
                    ->relationship('professor', 'nome', modifyQueryUsing: fn (Builder $query) => $query->whereHas('users', fn ($q) => $q->role('professor')))
                    ->label('Professor')
                    ->searchable(['nome', 'cpf'])
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome.($record->cpf ? " - {$record->cpf}" : ''))
                    ->preload()
                    ->default(auth()->user()?->hasRole('professor') ? auth()->user()->pessoa?->id : null)
                    ->disabled(auth()->user()?->hasRole('professor') && auth()->user()->pessoa?->id !== null)
                    ->dehydrated(),

                DatePicker::make('data')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required(),

                TimePicker::make('hora_inicio')
                    ->label('Hora de Início')
                    ->native(false)
                    ->seconds(false)
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::calcularHoraFim($get, $set);
                    }),

                TimePicker::make('hora_fim')
                    ->label('Hora de Fim')
                    ->native(false)
                    ->seconds(false)
                    ->helperText('Se não preenchida, será calculada automaticamente com base nos minutos por período do curso.'),

                Textarea::make('conteudo_ministrado')
                    ->label('Conteúdo Ministrado')
                    ->rows(3),

                Grid::make(2)
                    ->schema([
                        Toggle::make('replicar_periodo')
                            ->label('Replicar aulas até o final do período letivo')
                            ->helperText('Cria automaticamente aulas recorrentes até a data final do período letivo selecionado, pulando dias não letivos.')
                            ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                            ->default(false)
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if ($state && empty($get('dias_semana')) && $get('data')) {
                                    $dayOfWeek = Carbon::parse($get('data'))->dayOfWeek;
                                    $set('dias_semana', [$dayOfWeek]);
                                }
                            }),

                        CheckboxList::make('dias_semana')
                            ->label('Dias da semana para replicação')
                            ->options([
                                0 => 'Domingo',
                                1 => 'Segunda-feira',
                                2 => 'Terça-feira',
                                3 => 'Quarta-feira',
                                4 => 'Quinta-feira',
                                5 => 'Sexta-feira',
                                6 => 'Sábado',
                            ])
                            ->columns(4)
                            ->visible(fn (Get $get, $livewire) => $livewire instanceof CreateRecord && $get('replicar_periodo'))
                            ->required(fn (Get $get) => $get('replicar_periodo'))
                            ->dehydrated(false)
                            ->default(function (Get $get) {
                                try {
                                    if ($get('data')) {
                                        return [Carbon::parse($get('data'))->dayOfWeek];
                                    }
                                } catch (\Exception $e) {
                                }

                                return [];
                            }),
                    ]),
            ]);
    }

    /**
     * Calcula a hora_fim com base na hora_inicio e nos minutos_por_periodo do curso
     * vinculado através da cadeia: turma_id → turma → serie → curso.
     */
    protected static function calcularHoraFim(Get $get, Set $set): void
    {
        // Só preenche se hora_fim ainda estiver vazia
        if ($get('hora_fim')) {
            return;
        }

        $turmaId = $get('turma_id');
        $horaInicio = $get('hora_inicio');

        if (! $turmaId || ! $horaInicio) {
            return;
        }

        // Navega: turma → serie → curso → minutos_por_periodo
        $turma = Turma::with('serie.curso')->find($turmaId);
        $minutos = $turma?->serie?->curso?->minutos_por_periodo;

        if (! $minutos) {
            return;
        }

        // Converte a string HH:MM para um timestamp relativo e soma os minutos
        try {
            $inicio = Carbon::createFromFormat('H:i', substr($horaInicio, 0, 5));
            $fim = $inicio->addMinutes($minutos);
            $set('hora_fim', $fim->format('H:i'));
        } catch (\Exception $e) {
            // Formato inesperado — deixa o campo em branco
        }
    }
}
