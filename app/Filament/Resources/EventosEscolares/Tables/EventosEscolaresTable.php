<?php

namespace App\Filament\Resources\EventosEscolares\Tables;

use App\Enums\TipoEventoEscolar;
use App\Models\EventoEscolar;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EventosEscolaresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Evento')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tipo')
                    ->label('Categoria')
                    ->badge()
                    ->sortable(),

                TextColumn::make('data_inicio')
                    ->label('Data / Horário')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('local')
                    ->label('Local')
                    ->placeholder('Não especificado')
                    ->limit(25),

                TextColumn::make('confirmados_badge')
                    ->label('Confirmados')
                    ->badge()
                    ->color('success')
                    ->state(function (EventoEscolar $record) {
                        $total = $record->total_confirmados;
                        $vagas = $record->limite_vagas ? "/{$record->limite_vagas}" : '';

                        return "{$total}{$vagas}";
                    }),

                IconColumn::make('exige_autorizacao')
                    ->label('Autorização')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('data_inicio', 'desc')
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo de Evento')
                    ->options(TipoEventoEscolar::class),

                TernaryFilter::make('ativo')
                    ->label('Status do Evento')
                    ->placeholder('Todos')
                    ->trueLabel('Apenas Ativos')
                    ->falseLabel('Apenas Inativos'),
            ])
            ->recordActions([
                Action::make('participantes')
                    ->label('Lista de Presença')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->modalHeading(fn (EventoEscolar $record) => "Participantes: {$record->titulo}")
                    ->modalDescription('Relação de presenças confirmadas e termos de autorização assinados pelas famílias.')
                    ->infolist([
                        TextEntry::make('total_resumo')
                            ->label('Resumo Geral')
                            ->state(fn (EventoEscolar $record) => "Total Confirmados: {$record->total_confirmados} pessoas"),

                        RepeatableEntry::make('confirmacoes')
                            ->label('Respostas dos Responsáveis')
                            ->schema([
                                TextEntry::make('matricula.pessoa.nome')
                                    ->label('Aluno(a)'),

                                TextEntry::make('responsavel.nome')
                                    ->label('Responsável'),

                                TextEntry::make('status')
                                    ->label('RSVP')
                                    ->badge(),

                                TextEntry::make('quantidade_acompanhantes')
                                    ->label('Acompanhantes'),

                                IconEntry::make('autorizado')
                                    ->label('Autorizado?')
                                    ->boolean(),

                                TextEntry::make('data_resposta')
                                    ->label('Data Resposta')
                                    ->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(6),
                    ]),

                EditAction::make(),
            ])
            ->stackedOnMobile();
    }
}
