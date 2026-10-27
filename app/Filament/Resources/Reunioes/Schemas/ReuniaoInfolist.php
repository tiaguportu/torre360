<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes\Schemas;

use App\Models\Reuniao;
use App\Models\ReuniaoParticipante;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReuniaoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reunião')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('titulo')->label('Título')->weight('bold')->columnSpan(2),
                    TextEntry::make('status')->label('Situação')->badge(),
                    TextEntry::make('tipo')->label('Tipo')->badge(),
                    TextEntry::make('inicio_em')->label('Início')->dateTime('d/m/Y H:i'),
                    TextEntry::make('fim_em')->label('Fim')->dateTime('H:i')->placeholder('—'),
                    TextEntry::make('local')->label('Local')->placeholder('—'),
                    TextEntry::make('organizador.name')->label('Organizador')->placeholder('—'),
                    TextEntry::make('sigilo')->label('Sigilo')->badge()
                        ->state(fn (Reuniao $record): string => $record->ehSigilosa() ? 'Sigilosa' : 'Aberta à coordenação')
                        ->color(fn (Reuniao $record): string => $record->ehSigilosa() ? 'warning' : 'gray'),
                    TextEntry::make('turma.nome')->label('Turma')->visible(fn (Reuniao $record): bool => $record->ehConselho())->placeholder('—'),
                    TextEntry::make('etapaAvaliativa.nome')->label('Etapa')->visible(fn (Reuniao $record): bool => $record->ehConselho())->placeholder('—'),
                    TextEntry::make('realizada_em')->label('Realizada em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('motivo_cancelamento')->label('Motivo do cancelamento')->columnSpanFull()
                        ->visible(fn (Reuniao $record): bool => filled($record->motivo_cancelamento)),
                    TextEntry::make('pauta')->label('Pauta')->columnSpanFull()->placeholder('Sem pauta registrada.')->prose(),
                ]),
            Section::make('Participantes')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('participantes')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('user.name')->label('Nome')->weight('bold'),
                            TextEntry::make('papel')->label('Papel')
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    ReuniaoParticipante::PAPEL_ORGANIZADOR => 'Organizador',
                                    ReuniaoParticipante::PAPEL_PRESIDENTE => 'Presidente',
                                    default => 'Participante',
                                }),
                            TextEntry::make('convite')->label('Convite')->badge(),
                            IconEntry::make('presente')->label('Presença')->boolean()
                                ->trueIcon('heroicon-o-check-circle')->falseIcon('heroicon-o-x-circle')
                                ->placeholder('Não registrada'),
                        ]),
                ]),
            Section::make('Ata')
                ->columnSpanFull()
                ->description(fn (Reuniao $record): string => $record->ataEstaFechada()
                    ? 'Ata fechada em '.$record->ata_fechada_em?->format('d/m/Y H:i').($record->ataFechadaPor ? ' por '.$record->ataFechadaPor->name : '').'. Ela não muda mais: correções entram como adendo.'
                    : 'A ata está em aberto e pode ser editada até ser fechada.')
                ->visible(fn (Reuniao $record): bool => ! $record->estaCancelada() && ($record->emAndamento() || $record->ataEstaFechada()))
                ->schema([
                    TextEntry::make('ata')->hiddenLabel()->placeholder('A ata ainda não foi escrita.')->prose()->columnSpanFull(),
                    RepeatableEntry::make('adendos')
                        ->label('Adendos')
                        ->visible(fn (Reuniao $record): bool => $record->adendos->isNotEmpty())
                        ->columns(3)
                        ->schema([
                            TextEntry::make('texto')->hiddenLabel()->columnSpan(2)->prose(),
                            TextEntry::make('registro')->hiddenLabel()
                                ->state(fn ($record): string => ($record->autor?->name ?? 'Sistema').' em '.$record->created_at->format('d/m/Y H:i')),
                        ]),
                ]),
            Section::make('Assinatura da ata')
                ->columnSpanFull()
                ->columns(3)
                ->visible(fn (Reuniao $record): bool => $record->temCopiaAssinada())
                ->schema([
                    TextEntry::make('assinado_em')->label('Assinada em')->dateTime('d/m/Y H:i'),
                    TextEntry::make('assinatura_nivel')->label('Nível da assinatura')->badge(),
                ]),
        ]);
    }
}
