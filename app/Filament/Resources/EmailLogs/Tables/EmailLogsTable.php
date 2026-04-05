<?php

namespace App\Filament\Resources\EmailLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class EmailLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('user.name')
                    ->label('Quem enviou')
                    ->placeholder('Sistema')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('to')
                    ->label('Para')
                    ->searchable()
                    ->bulleted(),
                \Filament\Tables\Columns\TextColumn::make('subject')
                    ->label('Assunto')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('sent_at')
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
