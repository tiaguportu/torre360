<?php

namespace App\Filament\Resources\FrequenciaEscolars\Schemas;

use App\Models\FrequenciaEscolar;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->live(onBlur: true)
                    ->required(),
                self::secaoJustificativa(),
            ]);
    }

    /**
     * Justificar e abonar uma falta isolada. Justificar não tira a falta da frequência; abonar tira (e exige justificar,
     * com o motivo registrado). A falta justificada por um atestado é alterada pelo atestado, não aqui.
     */
    private static function secaoJustificativa(): Section
    {
        $doAtestado = fn (?FrequenciaEscolar $record): bool => $record?->atestado_aluno_id !== null;

        return Section::make('Justificativa e abono da falta')
            ->description('Só para falta (ausente). Falta justificada continua contando na frequência; só a falta abonada deixa de contar.')
            ->visible(fn (Get $get): bool => $get('situacao') === 'ausente')
            ->columns(2)
            ->schema([
                Toggle::make('justificada')
                    ->label('Falta justificada')
                    ->helperText(fn (?FrequenciaEscolar $record): ?string => $record?->atestado_aluno_id !== null ? 'Justificada por um atestado: altere o período ou o abono pelo atestado (Atestados e Abono de Faltas).' : null)
                    ->live()
                    ->disabled($doAtestado),
                Toggle::make('abonada')
                    ->label('Registrar como abono de falta')
                    ->helperText('Marcado, a falta não conta na frequência. Desmarcado, fica só justificada.')
                    ->visible(fn (Get $get): bool => (bool) $get('justificada'))
                    // Escondido (justificativa removida), o abono vai junto: abonar sem justificar não vale.
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn ($state, Get $get): bool => (bool) $get('justificada') && (bool) $state)
                    ->disabled($doAtestado),
                TextInput::make('motivo_justificativa')
                    ->label('Motivo da justificativa')
                    ->helperText('Obrigatório para justificar. Não escreva diagnóstico: basta dizer, por exemplo, "consulta médica".')
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => (bool) $get('justificada'))
                    ->visible(fn (Get $get): bool => (bool) $get('justificada'))
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn ($state, Get $get): ?string => $get('justificada') ? $state : null)
                    ->disabled($doAtestado)
                    ->columnSpanFull(),
            ]);
    }
}
