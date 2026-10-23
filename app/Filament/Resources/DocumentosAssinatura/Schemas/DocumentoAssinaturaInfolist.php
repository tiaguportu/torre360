<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosAssinatura\Schemas;

use App\Models\DocumentoAssinatura;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Página do documento: situação, quem assina e o que cada um já fez, o erro do envio (se houve) e a cópia do texto que
 * foi para o Assinafy.
 */
class DocumentoAssinaturaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Documento')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('titulo')
                            ->label('Título')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label('Situação')
                            ->badge(),
                        TextEntry::make('template.nome')
                            ->label('Modelo'),
                        TextEntry::make('tipo_do_registro')
                            ->label('Referente a')
                            ->state(fn (DocumentoAssinatura $record): string => $record->tipoDoRegistro()),
                        TextEntry::make('expira_em')
                            ->label('Prazo para assinar')
                            ->date('d/m/Y')
                            ->placeholder('Sem prazo'),
                        TextEntry::make('enviado_em')
                            ->label('Enviado em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Ainda não enviado'),
                        TextEntry::make('concluido_em')
                            ->label('Assinado por todos em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('criadoPor.name')
                            ->label('Criado por')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('arquivo_original_hash')
                            ->label('Impressão digital do PDF enviado (SHA-256)')
                            ->helperText('Serve para provar que o PDF guardado aqui é o mesmo que foi para assinatura.')
                            ->copyable()
                            ->fontFamily('mono')
                            ->size('xs')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
                Section::make('Assinatura digital')
                    ->description('O que o sistema leu no PDF assinado. Ele não valida a cadeia de certificação, a revogação nem a validade do certificado: para essa garantia, use o Verificador de Conformidade do ITI (validar.iti.gov.br).')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('success')
                    ->columns(3)
                    ->visible(fn (DocumentoAssinatura $record): bool => $record->nivel_assinatura !== null)
                    ->schema([
                        TextEntry::make('nivel_assinatura')
                            ->label('Nível encontrado')
                            ->badge()
                            ->columnSpan(2),
                        TextEntry::make('origem_da_assinatura')
                            ->label('Origem')
                            ->state(fn (DocumentoAssinatura $record): string => $record->foiAssinadoFora() ? 'Assinado fora do sistema e anexado' : 'Assinado pelo Assinafy'),
                        TextEntry::make('assinaturas_no_pdf')
                            ->label('Assinaturas no PDF')
                            ->state(fn (DocumentoAssinatura $record): string => (string) ($record->assinatura_detalhes['assinaturas'] ?? 0)),
                        TextEntry::make('formatos_da_assinatura')
                            ->label('Formato')
                            ->state(fn (DocumentoAssinatura $record): string => implode(', ', $record->assinatura_detalhes['formatos'] ?? []) ?: '—'),
                        TextEntry::make('conferencia_do_original')
                            ->label('Parte do PDF emitido pela escola')
                            ->state(function (DocumentoAssinatura $record): string {
                                $contem = $record->assinatura_detalhes['contem_original'] ?? null;

                                return match ($contem) {
                                    true => 'Sim, o arquivo começa pelo PDF emitido',
                                    false => 'Não confere: confira se é o mesmo documento',
                                    default => 'Não conferido',
                                };
                            }),
                        TextEntry::make('hash_assinado')
                            ->label('Impressão digital do PDF assinado (SHA-256)')
                            ->state(fn (DocumentoAssinatura $record): ?string => $record->assinatura_detalhes['hash_sha256'] ?? null)
                            ->copyable()
                            ->fontFamily('mono')
                            ->size('xs')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
                Section::make('Aviso do envio')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->visible(fn (DocumentoAssinatura $record): bool => filled($record->assinafy_log['erro'] ?? null) || (bool) ($record->assinafy_log['assinado_apos_cancelamento'] ?? false))
                    ->schema([
                        TextEntry::make('erro')
                            ->hiddenLabel()
                            ->state(fn (DocumentoAssinatura $record): string => filled($record->assinafy_log['erro'] ?? null)
                                ? 'O Assinafy recusou o envio: '.$record->assinafy_log['erro'].' Corrija o que for preciso e use "Enviar para assinatura" de novo; o que já foi cadastrado lá não se repete.'
                                : 'Este documento foi cancelado aqui, mas o Assinafy registrou que ele foi assinado. Confira no Assinafy antes de usá-lo.'),
                    ]),
                Section::make('Assinantes')
                    ->visible(fn (DocumentoAssinatura $record): bool => ! $record->foiAssinadoFora())
                    ->schema([
                        RepeatableEntry::make('assinantes')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('nome')->label('Nome'),
                                TextEntry::make('papel')->label('Papel')->placeholder('—'),
                                TextEntry::make('email')->label('E-mail'),
                                TextEntry::make('status')->label('Situação')->badge(),
                                TextEntry::make('assinado_em')->label('Assinou em')->dateTime('d/m/Y H:i')->placeholder('—'),
                            ])
                            ->columns(5),
                    ]),
                Section::make('Texto enviado')
                    ->description('Cópia do que foi para o Assinafy. Mudanças feitas no modelo depois do envio não alteram este documento.')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('conteudo')
                            ->hiddenLabel()
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
