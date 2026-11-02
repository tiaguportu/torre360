<?php

namespace App\Filament\Resources\Denuncias\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DenunciaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Manifestação')
                ->columns(3)
                ->schema([
                    TextEntry::make('protocolo')->label('Protocolo')->weight('bold'),
                    TextEntry::make('categoria')->label('Assunto')->badge(),
                    TextEntry::make('status')->label('Situação')->badge(),
                    TextEntry::make('prazo_em')->label('Prazo da primeira providência')->dateTime('d/m/Y')->color(fn ($record): ?string => $record->atrasada() ? 'danger' : null),
                    TextEntry::make('responsavel.name')->label('Responsável pela apuração')->placeholder('Ninguém assumiu'),
                    TextEntry::make('anonima')->label('Autoria')->formatStateUsing(fn (bool $state): string => $state ? 'Anônima' : 'Identificada')->badge(),
                    TextEntry::make('suspeita_violencia')
                        ->label('Atenção')
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Suspeita de violência contra criança ou adolescente: siga o fluxo de comunicação ao Conselho Tutelar.' : '—')
                        ->color(fn (bool $state): ?string => $state ? 'danger' : null)
                        ->columnSpanFull(),
                    TextEntry::make('descricao')->label('Relato')->columnSpanFull()->prose(),
                    TextEntry::make('local_data')->label('Onde e quando')->placeholder('—')->columnSpanFull(),
                    // `state()` devolve um texto pronto: um array no estado do TextEntry viraria uma lista de itens.
                    TextEntry::make('envolvidos')
                        ->label('Pessoas envolvidas')
                        ->placeholder('—')
                        ->state(fn ($record): ?string => collect($record->envolvidos ?? [])->map(fn (array $e): string => ($e['nome'] ?? '?').' ('.($e['papel'] ?? '—').')')->implode('; ') ?: null)
                        ->columnSpanFull(),
                    TextEntry::make('identificacao')
                        ->label('Identificação do autor')
                        ->visible(fn ($record): bool => ! $record->anonima)
                        ->state(fn ($record): string => collect($record->identificacao ?? [])->map(fn ($valor, $campo): string => "{$campo}: {$valor}")->implode(' · '))
                        ->columnSpanFull(),
                    TextEntry::make('conclusao')->label('Conclusão')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Histórico')
                ->schema([
                    RepeatableEntry::make('movimentacoes')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('tipo')->label('Tipo')->badge(),
                            TextEntry::make('created_at')->label('Quando')->dateTime('d/m/Y H:i'),
                            TextEntry::make('user.name')->label('Por')->placeholder('Autor / sistema'),
                            TextEntry::make('visivel_ao_autor')->label('O autor vê')->formatStateUsing(fn (bool $state): string => $state ? 'Sim' : 'Não'),
                            TextEntry::make('texto')->label('Texto')->columnSpanFull()->prose(),
                        ])
                        ->columns(4),
                ]),
        ]);
    }
}
