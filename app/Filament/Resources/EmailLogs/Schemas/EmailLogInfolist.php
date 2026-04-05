<?php

namespace App\Filament\Resources\EmailLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmailLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalhes do E-mail')
                    ->components([
                        TextEntry::make('user.name')
                            ->label('Remetente (Usuário)')
                            ->placeholder('Sistema'),
                        TextEntry::make('sent_at')
                            ->label('Data de Envio')
                            ->dateTime(),
                        TextEntry::make('to')
                            ->label('Para')
                            ->bulleted(),
                        TextEntry::make('cc')
                            ->label('CC')
                            ->bulleted()
                            ->visible(fn ($record) => ! empty($record->cc)),
                        TextEntry::make('bcc')
                            ->label('BCC')
                            ->bulleted()
                            ->visible(fn ($record) => ! empty($record->bcc)),
                        TextEntry::make('subject')
                            ->label('Assunto'),
                    ])->columns(2),

                Section::make('Conteúdo')
                    ->components([
                        TextEntry::make('body')
                            ->label('')
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
