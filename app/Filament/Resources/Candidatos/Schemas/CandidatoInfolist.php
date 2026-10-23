<?php

declare(strict_types=1);

namespace App\Filament\Resources\Candidatos\Schemas;

use App\Models\Candidato;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CandidatoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Perfil do Candidato')
                    ->columns(4)
                    ->schema([
                        ImageEntry::make('foto_path')
                            ->label('Foto')
                            ->disk('public')
                            ->circular()
                            ->defaultImageUrl('https://ui-avatars.com/api/?name=C&background=e2e8f0&color=64748b'),

                        TextEntry::make('nome_completo')
                            ->label('Nome Completo')
                            ->weight('bold')
                            ->columnSpan(2),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),

                        TextEntry::make('cargo_pretendido')
                            ->label('Vaga Pretendida')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('email')
                            ->label('E-mail')
                            ->placeholder('—'),

                        TextEntry::make('telefone')
                            ->label('Telefone / WhatsApp')
                            ->placeholder('—'),

                        TextEntry::make('idade')
                            ->label('Idade')
                            ->state(fn (Candidato $record): string => $record->idade_calculada ? "{$record->idade_calculada} anos" : '—'),

                        TextEntry::make('local_moradia')
                            ->label('Local onde mora')
                            ->placeholder('—'),

                        TextEntry::make('escola_atual')
                            ->label('Escola Atual')
                            ->placeholder('—'),

                        TextEntry::make('pretensao_salarial')
                            ->label('Pretensão Salarial')
                            ->money('BRL')
                            ->placeholder('—'),

                        TextEntry::make('entrevistador.name')
                            ->label('Entrevistador')
                            ->placeholder('—'),
                    ]),

                Section::make('🎓 Qualificações e Experiências')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('formacao_academica')
                            ->label('Formação Acadêmica')
                            ->placeholder('—'),

                        TextEntry::make('experiencias_profissionais')
                            ->label('Experiências Anteriores')
                            ->placeholder('—'),

                        TextEntry::make('habilidades')
                            ->label('Habilidades & Diferenciais')
                            ->placeholder('—'),

                        TextEntry::make('resumo_perfil')
                            ->label('Síntese do Perfil (IA)')
                            ->placeholder('—'),
                    ]),

                Section::make('🎙️ Entrevista de RH & Análise de Áudio (WhatsApp)')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('impressoes_entrevistador')
                            ->label('Impressões do Entrevistador de RH')
                            ->placeholder('Nenhuma impressão registrada ainda.')
                            ->columnSpanFull(),

                        TextEntry::make('audio_transcricao_resumo')
                            ->label('Resumo e Transcrição do Áudio (IA)')
                            ->placeholder('Nenhum áudio processado.')
                            ->columnSpanFull(),

                        TextEntry::make('audio_entrevista_path')
                            ->label('Gravação de Áudio')
                            ->formatStateUsing(fn (?string $state): string => $state ? 'Ouvir / Baixar Gravação de WhatsApp' : 'Nenhum áudio anexado')
                            ->url(fn (Candidato $record): ?string => $record->audio_url, shouldOpenInNewTab: true)
                            ->color(fn (?string $state): string => $state ? 'purple' : 'gray'),

                        TextEntry::make('curriculo_path')
                            ->label('Currículo Original (Auditoria)')
                            ->formatStateUsing(fn (?string $state): string => $state ? 'Visualizar / Baixar Currículo Arquivado' : 'Nenhum currículo anexado')
                            ->url(fn (Candidato $record): ?string => $record->curriculo_url, shouldOpenInNewTab: true)
                            ->color(fn (?string $state): string => $state ? 'primary' : 'gray'),
                    ]),
            ]);
    }
}
