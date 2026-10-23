<?php

declare(strict_types=1);

namespace App\Filament\Resources\CasosFrequencia\Schemas;

use App\Models\CasoFrequencia;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CasoFrequenciaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aluno')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('matricula.pessoa.nome')->label('Aluno'),
                        TextEntry::make('matricula.turma.nome')->label('Turma')->placeholder('—'),
                        TextEntry::make('periodoLetivo.nome')->label('Período letivo')->placeholder('—'),
                        TextEntry::make('status')->label('Situação')->badge(),
                        TextEntry::make('created_at')->label('Aberto em')->dateTime('d/m/Y H:i'),
                        TextEntry::make('abertoPor.name')->label('Aberto por')->placeholder('—'),
                    ]),
                Section::make('Apuração')
                    ->description('Os números do dia em que o caso foi aberto (ou da última atualização). Faltas justificadas depois pedem "Atualizar apuração".')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('aulas_previstas')->label('Aulas previstas no ano'),
                        TextEntry::make('faltas')->label('Faltas'),
                        TextEntry::make('faltas_justificadas')->label('Faltas justificadas'),
                        TextEntry::make('faltas_injustificadas')
                            ->label('Faltas injustificadas')
                            ->state(fn (CasoFrequencia $record): int => max(0, $record->faltas - $record->faltas_justificadas)),
                        TextEntry::make('percentual_faltas')->label('Faltas sobre as aulas previstas')->suffix('%'),
                        TextEntry::make('limite_aulas')->label('Limite para notificar (aulas)'),
                    ]),
                Section::make('Conselho Tutelar')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('conselhoTutelar.nome')->label('Conselho')->placeholder('Não definido'),
                        TextEntry::make('notificado_em')->label('Ofício enviado em')->dateTime('d/m/Y')->placeholder('Ainda não enviado'),
                        TextEntry::make('protocolo_externo')->label('Protocolo do envio')->placeholder('—'),
                        TextEntry::make('resposta')->label('Resposta do Conselho')->placeholder('—')->columnSpan(2),
                        TextEntry::make('respondido_em')->label('Respondido em')->dateTime('d/m/Y')->placeholder('—'),
                    ]),
                Section::make('Encerramento')
                    ->visible(fn (CasoFrequencia $record): bool => ! $record->estaAberto())
                    ->columns(3)
                    ->schema([
                        TextEntry::make('encerrado_em')->label('Encerrado em')->dateTime('d/m/Y H:i'),
                        TextEntry::make('encerramento_motivo')->label('Motivo')->columnSpan(2),
                    ]),
                Section::make('Observação interna')
                    ->visible(fn (CasoFrequencia $record): bool => filled($record->observacao))
                    ->schema([
                        TextEntry::make('observacao')->hiddenLabel(),
                    ]),
            ]);
    }
}
