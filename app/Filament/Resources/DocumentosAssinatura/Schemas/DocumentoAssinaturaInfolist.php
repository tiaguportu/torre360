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
