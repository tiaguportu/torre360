<?php

namespace App\Filament\Resources\QuestionarioRespostas\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class QuestionarioRespostaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Envio')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('questionario.titulo')
                            ->label('Questionário')
                            ->weight('bold')
                            ->columnSpan(2),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'enviado' => 'success',
                                'pendente' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('user.name')
                            ->label('Respondente')
                            ->placeholder('Anônimo'),
                        TextEntry::make('perfil_institucional')
                            ->label('Perfil'),
                        TextEntry::make('fim_preenchimento')
                            ->label('Data de Envio')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make('Respostas Detalhadas')
                    ->schema([
                        RepeatableEntry::make('perguntaRespostas')
                            ->label('')
                            ->schema([
                                TextEntry::make('pergunta.enunciado')
                                    ->label('Pergunta')
                                    ->weight('bold'),
                                TextEntry::make('resposta_texto')
                                    ->label('Resposta')
                                    ->visible(fn ($record) => ! empty($record->resposta_texto)),
                                TextEntry::make('resposta_json')
                                    ->label('Opções Selecionadas')
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->visible(fn ($record) => ! empty($record->resposta_json)),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }
}
