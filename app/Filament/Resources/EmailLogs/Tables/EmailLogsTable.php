<?php

namespace App\Filament\Resources\EmailLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Quem enviou')
                    ->placeholder('Sistema')
                    ->sortable(),
                TextColumn::make('to')
                    ->label('Para')
                    ->searchable()
                    ->bulleted(),
                TextColumn::make('subject')
                    ->label('Assunto')
                    ->searchable(),
                TextColumn::make('sent_at')
                    ->label('Enviado em')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Read-only logs
            ]);
    }
}
