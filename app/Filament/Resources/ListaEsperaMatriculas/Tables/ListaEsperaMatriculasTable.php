<?php

namespace App\Filament\Resources\ListaEsperaMatriculas\Tables;

use App\Enums\StatusListaEspera;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListaEsperaMatriculasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable(),
                TextColumn::make('periodoLetivo.nome')
                    ->label('Período Letivo')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Na Fila Desde')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('notificado_em')
                    ->label('Notificado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusListaEspera::class),
                SelectFilter::make('turma_id')
                    ->label('Turma')
                    ->relationship('turma', 'nome')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('marcar_desistencia')
                    ->label('Marcar Desistência')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => in_array($record->status, [StatusListaEspera::Aguardando, StatusListaEspera::Notificado], true))
                    ->action(function ($record): void {
                        $record->update(['status' => StatusListaEspera::Desistiu]);

                        Notification::make()
                            ->title('Marcado como desistência.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at')
            ->stackedOnMobile();
    }
}
