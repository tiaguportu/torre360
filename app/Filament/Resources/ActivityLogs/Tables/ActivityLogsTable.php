<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->color(Color::Slate)
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Evento')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject_type')
                    ->label('Modelo')
                    ->formatStateUsing(fn ($state) => str($state)->afterLast('\\')->title())
                    ->searchable(),

                TextColumn::make('causer.name')
                    ->label('Usuário')
                    ->default('Sistema')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make()
                    ->form([
                        KeyValue::make('properties')
                            ->label('Propriedades Alteradas')
                            ->columnSpanFull(),
                    ])
                    ->modalHeading('Detalhes da Atividade')
                    ->modalWidth('2xl'),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Canal de Log')
                    ->options([
                        'default' => 'Padrão',
                        'auth' => 'Autenticação',
                        'shield' => 'Permissões (Shield)',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
