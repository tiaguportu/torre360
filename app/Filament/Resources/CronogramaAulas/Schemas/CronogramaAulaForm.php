<?php

namespace App\Filament\Resources\CronogramaAulas\Schemas;

use App\Models\Turma;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CronogramaAulaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('turma_id')
                    ->relationship('turma', 'nome')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome ?? "Turma #{$record->id}")
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::calcularHoraFim($get, $set);
                    }),

                Select::make('disciplina_id')
                    ->relationship('disciplina', 'nome')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('pessoa_id')
                    ->relationship('professor', 'nome', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereHas('perfis', fn ($q) => $q->where('nome', 'like', '%Professor%')))
                    ->label('Professor')
                    ->searchable(['nome', 'cpf'])
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ($record->cpf ? " - {$record->cpf}" : ""))
                    ->preload(),

                DatePicker::make('data')
                    ->required(),

                TimePicker::make('hora_inicio')
                    ->label('Hora de Início')
                    ->seconds(false)
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::calcularHoraFim($get, $set);
                    }),

                TimePicker::make('hora_fim')
                    ->label('Hora de Fim')
                    ->seconds(false)
                    ->helperText('Se não preenchida, será calculada automaticamente com base nos minutos por período do curso.'),

                Textarea::make('conteudo_ministrado')
                    ->label('Conteúdo Ministrado')
                    ->rows(3),
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

        $turmaId   = $get('turma_id');
        $horaInicio = $get('hora_inicio');

        if (!$turmaId || !$horaInicio) {
            return;
        }

        // Navega: turma → serie → curso → minutos_por_periodo
        $turma = Turma::with('serie.curso')->find($turmaId);
        $minutos = $turma?->serie?->curso?->minutos_por_periodo;

        if (!$minutos) {
            return;
        }

        // Converte a string HH:MM para um timestamp relativo e soma os minutos
        try {
            $inicio = \Carbon\Carbon::createFromFormat('H:i', substr($horaInicio, 0, 5));
            $fim    = $inicio->addMinutes($minutos);
            $set('hora_fim', $fim->format('H:i'));
        } catch (\Exception $e) {
            // Formato inesperado — deixa o campo em branco
        }
    }
}
