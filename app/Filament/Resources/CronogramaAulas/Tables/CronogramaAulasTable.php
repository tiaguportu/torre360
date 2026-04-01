<?php

namespace App\Filament\Resources\CronogramaAulas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CronogramaAulasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('turma.id')
                    ->label('Turma')
                    ->sortable(),
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('professor.nome')
                    ->label('Professor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('data')
                    ->date()
                    ->sortable(),
                TextColumn::make('hora_inicio')
                    ->time()
                    ->sortable(),
                TextColumn::make('hora_fim')
                    ->time(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->toolbarActions([
                Action::make('calendar')
                    ->label('Visualizar Calendário')
                    ->icon('heroicon-o-calendar')
                    ->url(fn (): string => \App\Filament\Resources\CronogramaAulas\CronogramaAulaResource::getUrl('calendar')),
            ]);
    }
}
