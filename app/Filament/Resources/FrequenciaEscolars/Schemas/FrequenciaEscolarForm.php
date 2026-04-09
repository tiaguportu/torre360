<?php

namespace App\Filament\Resources\FrequenciaEscolars\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class FrequenciaEscolarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('matricula_id')
                    ->label('Matrícula')
                    ->relationship('matricula', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->periodoLetivo?->nome} - {$record->turma?->nome} - {$record->pessoa?->nome}")
                    ->searchable()
                    ->required(),
                Select::make('cronograma_aula_id')
                    ->label('Cronograma de Aula')
                    ->relationship('cronogramaAula', 'id')
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        $data = Carbon::parse($record->data)->format('d/m/Y');
                        $inicio = Carbon::parse($record->hora_inicio)->format('H:i');
                        $fim = Carbon::parse($record->hora_fim)->format('H:i');
                        $turma = $record->turma?->nome ?? 'N/A';
                        $disciplina = $record->disciplina?->nome ?? 'N/A';
                        $professor = $record->professor?->nome ?? 'N/A';

                        return "{$data} ({$inicio}-{$fim}) - Turma: {$turma} - {$disciplina} ({$professor})";
                    })
                    ->searchable(['data', 'turma.nome', 'disciplina.nome', 'professor.nome'])
                    ->required(),
                TextInput::make('situacao')
                    ->required(),
            ]);
    }
}
